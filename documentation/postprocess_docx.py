import sys
from docx import Document
from docx.shared import Pt, Inches, RGBColor
from docx.oxml.ns import qn
from docx.oxml import OxmlElement
src, dst = sys.argv[1], sys.argv[2]
d = Document(src)
for s in d.sections:
    s.left_margin = s.right_margin = Inches(0.9); s.top_margin = s.bottom_margin = Inches(0.9)
def font(style, name=None, size=None, color=None, bold=None):
    try: st = d.styles[style]
    except KeyError: return
    if name:
        st.font.name = name
        rpr = st.element.get_or_add_rPr(); rf = rpr.find(qn('w:rFonts'))
        if rf is None: rf = OxmlElement('w:rFonts'); rpr.append(rf)
        for a in ('w:ascii','w:hAnsi','w:cs','w:eastAsia'): rf.set(qn(a), name)
    if size: st.font.size = Pt(size)
    if color: st.font.color.rgb = RGBColor.from_string(color)
    if bold is not None: st.font.bold = bold
for st in ('Normal','Body Text','First Paragraph','Compact'): font(st,'Calibri',10.5)
font('Title','Calibri',26,'1F3864',True); font('Subtitle','Calibri',14,'404040'); font('Author','Calibri',12); font('Date','Calibri',10)
font('Heading 1','Calibri',18,'1F3864',True); font('Heading 2','Calibri',14,'2F5496',True); font('Heading 3','Calibri',12,'2F5496',True)
for st in ('Verbatim Char','Source Code'): font(st,'Consolas',7.5)
def borders(tbl):
    pr = tbl._tbl.tblPr
    b = OxmlElement('w:tblBorders')
    for e in ('top','left','bottom','right','insideH','insideV'):
        x = OxmlElement(f'w:{e}'); x.set(qn('w:val'),'single'); x.set(qn('w:sz'),'4'); x.set(qn('w:color'),'A6A6A6'); b.append(x)
    pr.append(b)
for t in d.tables:
    borders(t)
    for i, row in enumerate(t.rows):
        for c in row.cells:
            for p in c.paragraphs:
                for r in p.runs: r.font.size = Pt(9)
            if i == 0:
                sh = OxmlElement('w:shd'); sh.set(qn('w:val'),'clear'); sh.set(qn('w:fill'),'D9E2F3'); c._tc.get_or_add_tcPr().append(sh)
                for p in c.paragraphs:
                    for r in p.runs: r.font.bold = True
for p in d.paragraphs:                      # keep images inside the page
    for r in p.runs:
        for inl in r._element.findall('.//' + qn('wp:inline')):
            ext = inl.find(qn('wp:extent')); 
            if ext is not None and int(ext.get('cx')) > Inches(6.6):
                ratio = Inches(6.6) / int(ext.get('cx')); ext.set('cx', str(int(Inches(6.6)))); ext.set('cy', str(int(int(ext.get('cy')) * ratio)))
                for e in inl.iter(qn('a:ext')):
                    if e.get('cx'): e.set('cx', str(int(Inches(6.6)))); e.set('cy', str(int(int(e.get('cy')) * ratio)))
# ask Word to refresh the table of contents when the file is opened
st = d.settings.element
uf = OxmlElement('w:updateFields'); uf.set(qn('w:val'), 'true'); st.append(uf)
d.save(dst)
