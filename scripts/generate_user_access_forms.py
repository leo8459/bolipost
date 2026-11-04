from pathlib import Path
import re
import struct
from itertools import count
from zipfile import ZIP_DEFLATED, ZipFile
from xml.sax.saxutils import escape


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "public/formatos"
OUTPUT.mkdir(parents=True, exist_ok=True)
MINISTERIO_LOGO = ROOT / "public/images/ministerio-obras-publicas.png"
HEADER_STRIP = ROOT / "public/images/encabezado_contratos.jpeg"
CONTROL_IDS = count(1000)
EMAIL_RECIPIENTS = [
    "marco.espinoza@correos.gob.bo",
    "leonardo.doriamedina@correos.gob.bo",
    "eynar.quispe@correos.gob.bo",
]


def run(text, size=18, bold=False, color="263B57", italic=False):
    props = [
        '<w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/>',
        f'<w:color w:val="{color}"/>',
        f'<w:sz w:val="{size}"/>',
    ]
    if bold:
        props.append("<w:b/>")
    if italic:
        props.append("<w:i/>")
    return f'<w:r><w:rPr>{"".join(props)}</w:rPr><w:t xml:space="preserve">{escape(text)}</w:t></w:r>'


def paragraph(text="", size=18, bold=False, color="263B57", before=0, after=80, align=None, italic=False):
    ppr = [f'<w:spacing w:before="{before}" w:after="{after}"/>']
    if align:
        ppr.append(f'<w:jc w:val="{align}"/>')
    if text:
        content = run(text, size=size, bold=bold, color=color, italic=italic)
    else:
        content = '<w:r><w:rPr><w:sz w:val="18"/></w:rPr><w:t xml:space="preserve"> </w:t></w:r>'
    return f'<w:p><w:pPr>{"".join(ppr)}</w:pPr>{content}</w:p>'


def content_control(label, prompt="Haga clic aquí para escribir", multiline=False):
    control_id = next(CONTROL_IDS)
    tag = re.sub(r"[^a-z0-9]+", "_", label.lower()).strip("_") or f"campo_{control_id}"
    text_property = '<w:text w:multiLine="1"/>' if multiline else "<w:text/>"
    return (
        '<w:sdt><w:sdtPr>'
        f'<w:alias w:val="{escape(label)}"/><w:tag w:val="{escape(tag)}"/>'
        f'<w:id w:val="{control_id}"/>{text_property}</w:sdtPr>'
        '<w:sdtContent>'
        f'{run(prompt, size=16, color="8B98AA", italic=True)}'
        '</w:sdtContent></w:sdt>'
    )


def rich_content_control(label, prompt):
    control_id = next(CONTROL_IDS)
    tag = re.sub(r"[^a-z0-9]+", "_", label.lower()).strip("_") or f"campo_{control_id}"
    return (
        '<w:sdt><w:sdtPr>'
        f'<w:alias w:val="{escape(label)}"/><w:tag w:val="{escape(tag)}"/>'
        f'<w:id w:val="{control_id}"/><w:richText/></w:sdtPr><w:sdtContent>'
        f'{paragraph(prompt, size=16, color="8B98AA", italic=True, after=0)}'
        '</w:sdtContent></w:sdt>'
    )


def checkbox_control(label):
    control_id = next(CONTROL_IDS)
    tag = re.sub(r"[^a-z0-9]+", "_", label.lower()).strip("_") or f"opcion_{control_id}"
    return (
        '<w:sdt><w:sdtPr>'
        f'<w:alias w:val="{escape(label)}"/><w:tag w:val="{escape(tag)}"/>'
        f'<w:id w:val="{control_id}"/>'
        '<w14:checkbox><w14:checked w14:val="0"/>'
        '<w14:checkedState w14:val="2612" w14:font="Segoe UI Symbol"/>'
        '<w14:uncheckedState w14:val="2610" w14:font="Segoe UI Symbol"/>'
        '</w14:checkbox></w:sdtPr><w:sdtContent>'
        '<w:r><w:rPr><w:rFonts w:ascii="Segoe UI Symbol" w:hAnsi="Segoe UI Symbol"/>'
        '<w:color w:val="17477F"/><w:sz w:val="20"/></w:rPr><w:t>&#x2610;</w:t></w:r>'
        '</w:sdtContent></w:sdt>'
    )


def option_paragraph(options):
    pieces = []
    for index, label in enumerate(options):
        if index:
            pieces.append(run("     ", size=16, color="C1CBD8"))
        pieces.append(checkbox_control(label))
        pieces.append(run(f" {label}", size=17, color="334C6B"))
    return '<w:p><w:pPr><w:spacing w:before="0" w:after="0"/></w:pPr>' + "".join(pieces) + '</w:p>'


def email_paragraph(address, rel_id):
    link_run = (
        '<w:r><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/>'
        '<w:color w:val="1261A0"/><w:u w:val="single"/><w:sz w:val="17"/></w:rPr>'
        f'<w:t>{escape(address)}</w:t></w:r>'
    )
    return f'<w:p><w:pPr><w:spacing w:before="0" w:after="35"/></w:pPr><w:hyperlink r:id="{rel_id}" w:history="1">{link_run}</w:hyperlink></w:p>'


def image_dimensions(image_data, image_path):
    if image_data.startswith(b"\x89PNG\r\n\x1a\n"):
        return struct.unpack(">II", image_data[16:24])
    if image_data.startswith(b"\xff\xd8"):
        position = 2
        start_of_frame = {0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF}
        while position + 9 < len(image_data):
            if image_data[position] != 0xFF:
                position += 1
                continue
            while position < len(image_data) and image_data[position] == 0xFF:
                position += 1
            marker = image_data[position]
            position += 1
            if marker in {0xD8, 0xD9, 0x01, *range(0xD0, 0xD8)}:
                continue
            segment_length = int.from_bytes(image_data[position:position + 2], "big")
            if marker in start_of_frame:
                height, width = struct.unpack(">HH", image_data[position + 3:position + 7])
                return width, height
            position += segment_length
    raise ValueError(f"No se pudo leer el tamaño de la imagen: {image_path}")


def image_paragraph(rel_id, image_id, name, image_path, width=1828800):
    image_data = image_path.read_bytes()
    image_width, image_height = image_dimensions(image_data, image_path)
    height = round(width * image_height / image_width)
    description = escape(name)
    drawing = (
        '<w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
        f'<wp:extent cx="{width}" cy="{height}"/>'
        '<wp:effectExtent l="0" t="0" r="0" b="0"/>'
        f'<wp:docPr id="{image_id}" name="{description}" descr="{description}"/>'
        '<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>'
        '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        '<pic:pic><pic:nvPicPr>'
        f'<pic:cNvPr id="{image_id}" name="{description}" descr="{description}"/>'
        '<pic:cNvPicPr><a:picLocks noChangeAspect="1"/></pic:cNvPicPr></pic:nvPicPr>'
        f'<pic:blipFill><a:blip r:embed="{rel_id}"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
        f'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="{width}" cy="{height}"/></a:xfrm>'
        '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
        '</a:graphicData></a:graphic></wp:inline></w:drawing>'
    )
    return f'<w:p><w:pPr><w:spacing w:before="0" w:after="40"/></w:pPr><w:r>{drawing}</w:r></w:p>'


def border_xml(color="D4DFEC", size="6"):
    return "".join(
        f'<w:{edge} w:val="single" w:sz="{size}" w:space="0" w:color="{color}"/>'
        for edge in ("top", "left", "bottom", "right", "insideH", "insideV")
    )


def field_cell(label, width, height=760, shade=None):
    shading = f'<w:shd w:fill="{shade or "F9FBFE"}"/>'
    cell_props = (
        f'<w:tcW w:w="{width}" w:type="dxa"/>'
        f'<w:tcBorders>{border_xml()}</w:tcBorders>'
        '<w:tcMar><w:top w:w="120" w:type="dxa"/><w:left w:w="145" w:type="dxa"/>'
        '<w:bottom w:w="110" w:type="dxa"/><w:right w:w="145" w:type="dxa"/></w:tcMar>'
        f'{shading}<w:vAlign w:val="top"/>'
    )
    label_p = paragraph(label, size=17, bold=True, color="385578", after=75)
    is_photo = label.lower().startswith("pegue aquí")
    prompt = "Haga clic para escribir aquí..."
    if "fecha" in label.lower():
        prompt = "Escriba la fecha..."
    elif "firma" in label.lower():
        prompt = "Escriba el nombre y firme..."
    field = rich_content_control(label, "Pegue aquí la fotografía o captura del error") if is_photo else content_control(label, prompt, multiline=height >= 900)
    blank_p = f'<w:p><w:pPr><w:spacing w:before="0" w:after="0"/></w:pPr>{field}</w:p>' if not is_photo else field
    row_height = f'<w:trPr><w:trHeight w:val="{height}" w:hRule="atLeast"/></w:trPr>'
    return f'<w:tc><w:tcPr>{cell_props}</w:tcPr>{label_p}{blank_p}</w:tc>', row_height


def table(rows, widths, height=760, gap=0):
    grid = "".join(f'<w:gridCol w:w="{width}"/>' for width in widths)
    row_xml = []
    for labels in rows:
        cells = []
        row_h = ""
        for index, label in enumerate(labels):
            cell, row_h = field_cell(label, widths[index], height=height)
            cells.append(cell)
        row_xml.append(f'<w:tr>{row_h}{"".join(cells)}</w:tr>')
    props = (
        '<w:tblPr><w:tblW w:w="0" w:type="auto"/>'
        '<w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/>'
        '<w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders>'
        '<w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="70" w:type="dxa"/>'
        '<w:bottom w:w="80" w:type="dxa"/><w:right w:w="70" w:type="dxa"/></w:tblCellMar></w:tblPr>'
    )
    return f'<w:tbl>{props}<w:tblGrid>{grid}</w:tblGrid>{"".join(row_xml)}</w:tbl>'


def section(title):
    cell = (
        '<w:tcPr><w:tcW w:w="10466" w:type="dxa"/><w:shd w:fill="EAF1F9"/>'
        '<w:tcBorders><w:left w:val="single" w:sz="24" w:space="0" w:color="F3BD18"/></w:tcBorders>'
        '<w:tcMar><w:top w:w="95" w:type="dxa"/><w:left w:w="150" w:type="dxa"/>'
        '<w:bottom w:w="95" w:type="dxa"/><w:right w:w="120" w:type="dxa"/></w:tcMar></w:tcPr>'
    )
    heading = paragraph(title, size=20, bold=True, color="17477F", before=0, after=0)
    return (
        '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
        '<w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/>'
        '</w:tblBorders><w:tblCellMar><w:top w:w="120" w:type="dxa"/><w:bottom w:w="90" w:type="dxa"/></w:tblCellMar></w:tblPr>'
        '<w:tblGrid><w:gridCol w:w="10466"/></w:tblGrid>'
        f'<w:tr><w:tc>{cell}{heading}</w:tc></w:tr></w:tbl>'
    )


def options_cell(options, width=10466, height=620):
    props = (
        f'<w:tcW w:w="{width}" w:type="dxa"/><w:tcBorders>{border_xml()}</w:tcBorders>'
        '<w:tcMar><w:top w:w="80" w:type="dxa"/><w:left w:w="120" w:type="dxa"/>'
        '<w:bottom w:w="80" w:type="dxa"/><w:right w:w="120" w:type="dxa"/></w:tcMar>'
        '<w:vAlign w:val="center"/>'
    )
    row = f'<w:tr><w:trPr><w:trHeight w:val="{height}" w:hRule="atLeast"/></w:trPr><w:tc><w:tcPr>{props}</w:tcPr>{option_paragraph(options)}</w:tc></w:tr>'
    return (
        '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
        '<w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/>'
        '</w:tblBorders></w:tblPr><w:tblGrid><w:gridCol w:w="10466"/></w:tblGrid>'
        f'{row}</w:tbl>'
    )


def header(title, description):
    return (
        paragraph(title, size=38, bold=True, color="0B3E82", before=60, after=70)
        + paragraph(description, size=19, color="61728B", after=75)
        + paragraph("FORMULARIO INTERACTIVO  |  Complete los campos y marque las casillas en Word.", size=15, bold=True, color="A47A00", after=120)
    )


def document_body(kind):
    parts = []
    if kind == "creation":
        parts.append(header(
            "Solicitud de creación de usuario",
            "Complete este documento en Word, guárdelo y adjúntelo al correo institucional dirigido a la instancia responsable.",
        ))
        parts.append(section("1. Datos de la persona solicitante"))
        parts.append(table([
            ["Nombre completo", "Cédula de identidad"],
            ["Cargo", "Unidad / área"],
            ["Correo institucional", "Teléfono o interno"],
        ], [5233, 5233], height=650))
        parts.append(section("2. Datos de la cuenta solicitada"))
        parts.append(table([
            ["Nombre completo del usuario", "Cédula de identidad"],
            ["Cargo y unidad / área", "Regional / sucursal"],
            ["Correo institucional", "Alias sugerido (si corresponde)"],
        ], [5233, 5233], height=650))
        parts.append(section("3. Acceso requerido"))
        parts.append(table([["Rol que tendrá el usuario en SIOP"]], [10466], height=1050))
        parts.append(section("4. Justificación de la solicitud"))
        parts.append(table([["Explique brevemente la necesidad de crear esta cuenta"]], [10466], height=1050))
        parts.append(section("5. Autorización y registro"))
        parts.append(table([
            ["Nombre y firma de jefatura solicitante", "Fecha de solicitud"],
            ["Revisión / autorización", "Registro del administrador (uso interno)"],
        ], [5233, 5233], height=1050))
    elif kind == "changes":
        parts.append(header(
            "Solicitud de baja o edición de usuario",
            "Marque el tipo de solicitud, complete la identificación del usuario y describa el cambio requerido.",
        ))
        parts.append(section("1. Tipo de solicitud"))
        parts.append(options_cell(["Baja de usuario", "Edición / modificación de usuario"]))
        parts.append(section("2. Datos del usuario"))
        parts.append(table([
            ["Nombre completo", "Cédula de identidad"],
            ["Alias en SIOP", "Cargo y unidad / área"],
            ["Regional / sucursal", "Fecha efectiva solicitada"],
        ], [5233, 5233], height=650))
        parts.append(section("3. Detalle de la solicitud"))
        parts.append(table([
            ["Si es edición: dato, cargo, unidad o acceso actual", "Si es edición: cambio solicitado"],
        ], [5233, 5233], height=1050))
        parts.append(table([["Motivo de la baja o justificación del cambio"]], [10466], height=900))
        parts.append(options_cell(["Retirar todos los accesos", "No, indicar los accesos a retirar"]))
        parts.append(table([["Si solicita una baja parcial, indique qué accesos se deben retirar"]], [10466], height=650))
        parts.append(section("4. Autorización y registro"))
        parts.append(table([
            ["Nombre y firma de jefatura solicitante", "Fecha de solicitud"],
            ["Revisión / autorización", "Registro del administrador (uso interno)"],
        ], [5233, 5233], height=950))
    else:
        parts.append(header(
            "Solicitud de cambio del sistema SIOP",
            "Describa el cambio o problema encontrado. Si reporta un error, inserte una captura en el recuadro y adjúntela también al correo.",
        ))
        parts.append(section("1. Datos de quien solicita"))
        parts.append(table([
            ["Nombre completo", "Unidad / área"],
            ["Correo institucional", "Teléfono o interno"],
            ["Fecha del reporte", "Regional / sucursal"],
        ], [5233, 5233], height=650))
        parts.append(section("2. Módulo y tipo de solicitud"))
        parts.append(table([
            ["Módulo afectado en SIOP", "Pantalla o proceso relacionado"],
        ], [5233, 5233], height=800))
        parts.append(options_cell(["Reporte de error", "Cambio / mejora", "Nueva función", "Otro"]))
        parts.append(section("3. Descripción del cambio o error"))
        parts.append(table([["Describa el error o el cambio solicitado"]], [10466], height=1150))
        parts.append(table([[
            "Pasos para reproducir el error (si aplica)",
            "Resultado esperado y resultado obtenido",
        ]], [5233, 5233], height=1000))
        parts.append(section("4. Fotografía o captura del error"))
        parts.append(table([[
            "Pegue aquí la imagen del error en Word, o adjúntela como archivo al correo"
        ]], [10466], height=2800))
        parts.append(section("5. Prioridad y autorización"))
        parts.append(options_cell(["Prioridad baja", "Prioridad media", "Prioridad alta", "Crítica"]))
        parts.append(table([[
            "Nombre y firma de jefatura solicitante",
            "Fecha",
        ]], [5233, 5233], height=900))
    parts.append(paragraph(
        "Al terminar, guarde el formulario completado. Puede hacer clic en un correo para redactar su mensaje.",
        size=14, color="78869A", before=100, after=0, italic=True,
    ))
    parts.append(section("Enviar por correo a"))
    for index, address in enumerate(EMAIL_RECIPIENTS, start=3):
        parts.append(email_paragraph(address, f"rId{index}"))
    return "".join(parts)


CONTENT_TYPES = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="jpeg" ContentType="image/jpeg"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>
  <Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>
</Types>'''

ROOT_RELS = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>'''

DOCUMENT_RELS = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="mailto:marco.espinoza@correos.gob.bo" TargetMode="External"/>
  <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="mailto:leonardo.doriamedina@correos.gob.bo" TargetMode="External"/>
  <Relationship Id="rId5" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="mailto:eynar.quispe@correos.gob.bo" TargetMode="External"/>
</Relationships>'''

HEADER_RELS = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/encabezado_contratos.jpeg"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/ministerio-obras-publicas.png"/>
</Relationships>'''


def watermark_paragraph():
    width = 4_950_000
    height = 5_550_000
    anchor = (
        '<wp:anchor distT="0" distB="0" distL="0" distR="0" simplePos="0" '
        'relativeHeight="0" behindDoc="1" locked="0" layoutInCell="0" allowOverlap="1">'
        '<wp:simplePos x="0" y="0"/>'
        '<wp:positionH relativeFrom="page"><wp:posOffset>1305000</wp:posOffset></wp:positionH>'
        '<wp:positionV relativeFrom="page"><wp:posOffset>2570000</wp:posOffset></wp:positionV>'
        f'<wp:extent cx="{width}" cy="{height}"/><wp:effectExtent l="0" t="0" r="0" b="0"/>'
        '<wp:wrapNone/><wp:docPr id="2" name="Marca de agua institucional" descr="Escudo nacional en marca de agua"/>'
        '<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>'
        '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        '<pic:pic><pic:nvPicPr><pic:cNvPr id="2" name="Escudo nacional"/>'
        '<pic:cNvPicPr><a:picLocks noChangeAspect="1"/></pic:cNvPicPr></pic:nvPicPr>'
        '<pic:blipFill><a:blip r:embed="rId2"><a:srcRect l="3000" r="67000"/>'
        '<a:alphaModFix amt="6500"/></a:blip><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
        f'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="{width}" cy="{height}"/></a:xfrm>'
        '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
        '</a:graphicData></a:graphic></wp:anchor>'
    )
    return f'<w:p><w:pPr><w:spacing w:before="0" w:after="0"/></w:pPr><w:r><w:drawing>{anchor}</w:drawing></w:r></w:p>'


def word_header_xml():
    namespaces = (
        'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
        'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
        'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
        'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
        'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"'
    )
    logo = image_paragraph("rId1", 1, "Logos de Correos de Bolivia y del Ministerio de Obras Públicas", HEADER_STRIP, width=6648000)
    return f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:hdr {namespaces}>{logo}{watermark_paragraph()}</w:hdr>'


def word_footer_xml():
    def footer_paragraph(text, bold=False):
        props = '<w:spacing w:before="0" w:after="15"/><w:jc w:val="right"/><w:pBdr><w:right w:val="single" w:sz="20" w:space="10" w:color="C8A63A"/></w:pBdr>'
        return f'<w:p><w:pPr>{props}</w:pPr>{run(text, size=16 if bold else 13, bold=bold, color="444444")}</w:p>'

    colors = ("E63B32", "F3E52B", "168B4A")
    cells = "".join(
        '<w:tc><w:tcPr><w:tcW w:w="1300" w:type="dxa"/>'
        f'<w:shd w:fill="{color}"/><w:tcMar><w:top w:w="0" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/>'
        '<w:left w:w="0" w:type="dxa"/><w:right w:w="0" w:type="dxa"/></w:tcMar></w:tcPr>'
        '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="100" w:lineRule="exact"/></w:pPr>'
        '<w:r><w:t xml:space="preserve"> </w:t></w:r></w:p></w:tc>'
        for color in colors
    )
    flag = (
        '<w:tbl><w:tblPr><w:tblW w:w="3900" w:type="dxa"/><w:tblBorders>'
        '<w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/>'
        '<w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders>'
        '<w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid><w:gridCol w:w="1300"/>'
        '<w:gridCol w:w="1300"/><w:gridCol w:w="1300"/></w:tblGrid>'
        f'<w:tr><w:trPr><w:trHeight w:val="130" w:hRule="exact"/></w:trPr>{cells}</w:tr></w:tbl>'
    )
    namespaces = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
    return (
        f'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:ftr {namespaces}>'
        f'{footer_paragraph("CORREOS DE BOLIVIA", bold=True)}'
        f'{footer_paragraph("Zona Central - Av. Mariscal Santa Cruz, Esq. Calle Oruro, Edif. Centro de Comunicaciones")}'
        f'{footer_paragraph("Teléfonos: +591 (2) 2152423")}{flag}<w:p/></w:ftr>'
    )


def write_docx(path, kind):
    section = (
        '<w:sectPr><w:headerReference w:type="default" r:id="rId1"/>'
        '<w:footerReference w:type="default" r:id="rId2"/><w:pgSz w:w="11906" w:h="16838"/>'
        '<w:pgMar w:top="1300" w:right="720" w:bottom="1350" w:left="720" w:header="250" w:footer="250" w:gutter="0"/>'
        '</w:sectPr>'
    )
    xml = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
        'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
        'xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml" '
        'xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" mc:Ignorable="w14" '
        'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
        'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
        'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        f'<w:body>{document_body(kind)}{section}</w:body></w:document>'
    )
    with ZipFile(path, "w", ZIP_DEFLATED) as archive:
        archive.writestr("[Content_Types].xml", CONTENT_TYPES)
        archive.writestr("_rels/.rels", ROOT_RELS)
        archive.writestr("word/document.xml", xml)
        archive.writestr("word/_rels/document.xml.rels", DOCUMENT_RELS)
        archive.writestr("word/header1.xml", word_header_xml())
        archive.writestr("word/_rels/header1.xml.rels", HEADER_RELS)
        archive.writestr("word/footer1.xml", word_footer_xml())
        archive.writestr("word/media/encabezado_contratos.jpeg", HEADER_STRIP.read_bytes())
        archive.writestr("word/media/ministerio-obras-publicas.png", MINISTERIO_LOGO.read_bytes())


write_docx(OUTPUT / "solicitud-creacion-usuario.docx", "creation")
write_docx(OUTPUT / "solicitud-baja-edicion-usuario.docx", "changes")
write_docx(OUTPUT / "solicitud-cambio-sistema.docx", "system_changes")
