<?php
/** Validates an uploaded file: size, extension, real MIME type (finfo), magic bytes, no executables. */
class UploadValidator
{
    private const TYPES = [
        'pdf'  => ['mimes' => ['application/pdf']],
        'txt'  => ['mimes' => ['text/plain']],
        'docx' => ['mimes' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip']],
    ];
    private const BLOCKED_EXT = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'exe', 'dll', 'bat', 'cmd', 'com', 'msi', 'sh', 'bash',
        'js', 'jar', 'py', 'pl', 'cgi', 'asp', 'aspx', 'jsp', 'vbs', 'ps1', 'htaccess', 'html', 'htm', 'svg'];

    /**
     * @param array $file one entry of $_FILES
     * @param bool $requireHttpUpload set false only in automated tests
     * @return array{content:string,original_filename:string,file_type:string,mime_type:string,file_size:int}
     */
    public static function validate(array $file, bool $requireHttpUpload = true): array
    {
        if (!isset($file['error']) || is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $code = $file['error'] ?? -1;
            throw new ValidationException(in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'File is larger than the 10 MB limit.' : 'Upload failed. Please choose a file and try again.');
        }
        $tmp = (string)$file['tmp_name'];
        if ($requireHttpUpload && !is_uploaded_file($tmp)) {
            throw new ValidationException('Invalid upload.');
        }
        $size = (int)filesize($tmp);
        if ($size <= 0) {
            throw new ValidationException('The file is empty.');
        }
        if ($size > Config::MAX_UPLOAD_BYTES) {
            throw new ValidationException('File is larger than the 10 MB limit.');
        }

        // Clean the display name (metadata only; never used in a path).
        $name = basename(str_replace('\\', '/', (string)$file['name']));
        $name = preg_replace('/[\x00-\x1f\x7f]/u', '', $name) ?? '';
        $name = mb_substr($name, 0, 200);
        $parts = explode('.', strtolower($name));
        if (count($parts) < 2) {
            throw new ValidationException('The file needs an extension (.pdf, .docx or .txt).');
        }
        $ext = array_pop($parts);
        if (!isset(self::TYPES[$ext])) {
            throw new ValidationException('Only PDF, DOCX and TXT files are allowed.');
        }
        foreach ($parts as $p) {                       // catches "shell.php.pdf"
            if (in_array($p, self::BLOCKED_EXT, true)) {
                throw new ValidationException('This file name is not allowed.');
            }
        }

        $content = file_get_contents($tmp);
        if ($content === false) {
            throw new ValidationException('Could not read the uploaded file.');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->buffer($content);
        if (!in_array($mime, self::TYPES[$ext]['mimes'], true)) {
            throw new ValidationException('The file content does not match its extension.');
        }
        if ($ext === 'pdf' && !str_contains(substr($content, 0, 1024), '%PDF-')) {
            throw new ValidationException('The file is not a valid PDF.');
        }
        if ($ext === 'docx' && !str_starts_with($content, "PK\x03\x04")) {
            throw new ValidationException('The file is not a valid DOCX.');
        }
        if ($ext === 'txt' && str_contains($content, "\0")) {
            throw new ValidationException('The text file contains binary data.');
        }
        foreach (["MZ", "\x7FELF", "#!"] as $sig) {     // Windows/Linux executables and scripts
            if (str_starts_with($content, $sig)) {
                throw new ValidationException('Executable files are not allowed.');
            }
        }
        return ['content' => $content, 'original_filename' => $name, 'file_type' => $ext,
            'mime_type' => $ext === 'docx' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : $mime,
            'file_size' => $size];
    }
}
