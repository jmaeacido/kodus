from __future__ import annotations

import re
import textwrap
from pathlib import Path


PAGE_WIDTH = 612
PAGE_HEIGHT = 792
MARGIN_X = 52
MARGIN_TOP = 54
MARGIN_BOTTOM = 48


def escape_pdf_text(value: str) -> str:
    return value.replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)")


def normalize_inline_markdown(text: str) -> str:
    text = text.replace("**", "")
    text = text.replace("`", "")
    text = re.sub(r"\[([^\]]+)\]\([^)]+\)", r"\1", text)
    text = text.replace("  ", " ")
    return text.strip()


def parse_markdown(md_text: str) -> list[dict]:
    elements: list[dict] = []
    lines = md_text.splitlines()
    paragraph: list[str] = []

    def flush_paragraph() -> None:
        nonlocal paragraph
        if paragraph:
            joined = " ".join(part.strip() for part in paragraph if part.strip())
            if joined:
                elements.append({"type": "paragraph", "text": normalize_inline_markdown(joined)})
            paragraph = []

    for raw_line in lines:
        line = raw_line.rstrip()
        stripped = line.strip()

        if not stripped:
            flush_paragraph()
            elements.append({"type": "spacer", "height": 6})
            continue

        if stripped == "---":
            flush_paragraph()
            elements.append({"type": "rule"})
            continue

        if stripped.startswith("#"):
            flush_paragraph()
            level = len(stripped) - len(stripped.lstrip("#"))
            heading = normalize_inline_markdown(stripped[level:].strip())
            elements.append({"type": "heading", "level": level, "text": heading})
            continue

        if re.match(r"^\d+\.\s+", stripped):
            flush_paragraph()
            elements.append({"type": "list", "kind": "ordered", "text": normalize_inline_markdown(stripped)})
            continue

        if stripped.startswith("- "):
            flush_paragraph()
            elements.append({"type": "list", "kind": "bullet", "text": normalize_inline_markdown(stripped[2:])})
            continue

        if stripped.startswith("```"):
            flush_paragraph()
            elements.append({"type": "code_fence"})
            continue

        paragraph.append(stripped)

    flush_paragraph()
    return elements


def wrap_element(element: dict) -> list[dict]:
    etype = element["type"]

    if etype == "spacer":
        return [element]

    if etype == "rule":
        return [{"type": "rule", "height": 10}]

    if etype == "heading":
        level = element["level"]
        text = element["text"]
        if level == 1:
            width = 54
            font = "F2"
            size = 19
            leading = 24
            before = 6
            after = 10
        elif level == 2:
            width = 68
            font = "F2"
            size = 14
            leading = 18
            before = 6
            after = 6
        else:
            width = 78
            font = "F2"
            size = 11.5
            leading = 15
            before = 4
            after = 4

        lines = textwrap.wrap(text, width=width) or [text]
        return [
            {
                "type": "textblock",
                "lines": lines,
                "font": font,
                "size": size,
                "leading": leading,
                "indent": 0,
                "before": before,
                "after": after,
            }
        ]

    if etype == "paragraph":
        text = element["text"]
        lines = textwrap.wrap(text, width=92) or [text]
        return [
            {
                "type": "textblock",
                "lines": lines,
                "font": "F1",
                "size": 10.5,
                "leading": 14,
                "indent": 0,
                "before": 0,
                "after": 4,
            }
        ]

    if etype == "list":
        prefix = "- " if element["kind"] == "bullet" else ""
        text = element["text"]
        if element["kind"] == "ordered":
            m = re.match(r"^(\d+\.\s+)(.*)$", text)
            prefix = m.group(1) if m else ""
            text = m.group(2) if m else text
        width = 86 if element["kind"] == "bullet" else 84
        wrapped = textwrap.wrap(text, width=width) or [text]
        lines = []
        for idx, part in enumerate(wrapped):
            lines.append((prefix if idx == 0 else " " * len(prefix)) + part)
        return [
            {
                "type": "textblock",
                "lines": lines,
                "font": "F1",
                "size": 10.5,
                "leading": 14,
                "indent": 14,
                "before": 0,
                "after": 2,
            }
        ]

    if etype == "code_fence":
        return [{"type": "spacer", "height": 4}]

    return []


def paginate(blocks: list[dict]) -> list[list[dict]]:
    pages: list[list[dict]] = []
    current: list[dict] = []
    y = PAGE_HEIGHT - MARGIN_TOP
    usable_bottom = MARGIN_BOTTOM

    for block in blocks:
        if block["type"] == "spacer":
            needed = block["height"]
        elif block["type"] == "rule":
            needed = block.get("height", 10)
        else:
            needed = block["before"] + len(block["lines"]) * block["leading"] + block["after"]

        if y - needed < usable_bottom and current:
            pages.append(current)
            current = []
            y = PAGE_HEIGHT - MARGIN_TOP

        current.append(block)
        y -= needed

    if current:
        pages.append(current)

    return pages


def build_page_content(page_blocks: list[dict], page_number: int) -> bytes:
    commands: list[str] = []
    y = PAGE_HEIGHT - MARGIN_TOP

    for block in page_blocks:
        if block["type"] == "spacer":
            y -= block["height"]
            continue

        if block["type"] == "rule":
            y -= 4
            commands.append("0.75 w")
            commands.append(f"{MARGIN_X} {y:.2f} m {PAGE_WIDTH - MARGIN_X} {y:.2f} l S")
            y -= block.get("height", 10) - 4
            continue

        y -= block["before"]
        start_x = MARGIN_X + block.get("indent", 0)
        commands.append("BT")
        commands.append(f"/{block['font']} {block['size']:.2f} Tf")
        commands.append(f"{block['leading']:.2f} TL")
        commands.append(f"1 0 0 1 {start_x:.2f} {y:.2f} Tm")
        for idx, line in enumerate(block["lines"]):
            if idx > 0:
                commands.append("T*")
            commands.append(f"({escape_pdf_text(line)}) Tj")
        commands.append("ET")
        y -= len(block["lines"]) * block["leading"]
        y -= block["after"]

    footer = f"Page {page_number}"
    commands.append("BT")
    commands.append("/F1 9 Tf")
    commands.append(f"1 0 0 1 {PAGE_WIDTH - 92:.2f} {MARGIN_BOTTOM - 16:.2f} Tm")
    commands.append(f"({escape_pdf_text(footer)}) Tj")
    commands.append("ET")

    return "\n".join(commands).encode("latin-1", errors="replace")


def build_pdf(page_streams: list[bytes]) -> bytes:
    objects: list[bytes] = []

    def add_object(data: bytes | str) -> int:
        if isinstance(data, str):
            data = data.encode("latin-1", errors="replace")
        objects.append(data)
        return len(objects)

    font1_id = add_object("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>")
    font2_id = add_object("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>")

    page_ids: list[int] = []
    content_ids: list[int] = []

    placeholder_pages_id = add_object("<< /Type /Pages /Kids [] /Count 0 >>")

    for stream in page_streams:
        content_obj = (
            f"<< /Length {len(stream)} >>\nstream\n".encode("latin-1")
            + stream
            + b"\nendstream"
        )
        content_id = add_object(content_obj)
        content_ids.append(content_id)

        page_obj = (
            f"<< /Type /Page /Parent {placeholder_pages_id} 0 R "
            f"/MediaBox [0 0 {PAGE_WIDTH} {PAGE_HEIGHT}] "
            f"/Resources << /Font << /F1 {font1_id} 0 R /F2 {font2_id} 0 R >> >> "
            f"/Contents {content_id} 0 R >>"
        )
        page_ids.append(add_object(page_obj))

    kids = " ".join(f"{page_id} 0 R" for page_id in page_ids)
    objects[placeholder_pages_id - 1] = (
        f"<< /Type /Pages /Kids [{kids}] /Count {len(page_ids)} >>".encode("latin-1")
    )

    catalog_id = add_object(f"<< /Type /Catalog /Pages {placeholder_pages_id} 0 R >>")

    pdf = bytearray(b"%PDF-1.4\n%\xe2\xe3\xcf\xd3\n")
    offsets = [0]
    for index, obj in enumerate(objects, start=1):
        offsets.append(len(pdf))
        pdf.extend(f"{index} 0 obj\n".encode("latin-1"))
        pdf.extend(obj)
        pdf.extend(b"\nendobj\n")

    xref_offset = len(pdf)
    pdf.extend(f"xref\n0 {len(objects) + 1}\n".encode("latin-1"))
    pdf.extend(b"0000000000 65535 f \n")
    for offset in offsets[1:]:
        pdf.extend(f"{offset:010d} 00000 n \n".encode("latin-1"))

    pdf.extend(
        (
            f"trailer\n<< /Size {len(objects) + 1} /Root {catalog_id} 0 R >>\n"
            f"startxref\n{xref_offset}\n%%EOF\n"
        ).encode("latin-1")
    )

    return bytes(pdf)


def main() -> None:
    root = Path(__file__).resolve().parents[1]
    source = root / "docs" / "KODUS_FULL_DOCUMENTATION.md"
    target = root / "docs" / "KODUS_FULL_DOCUMENTATION.pdf"

    markdown_text = source.read_text(encoding="utf-8")
    elements = parse_markdown(markdown_text)

    blocks: list[dict] = []
    for element in elements:
        blocks.extend(wrap_element(element))

    pages = paginate(blocks)
    streams = [build_page_content(page, index + 1) for index, page in enumerate(pages)]
    pdf_bytes = build_pdf(streams)
    target.write_bytes(pdf_bytes)

    print(target)
    print(f"pages={len(pages)}")
    print(f"bytes={len(pdf_bytes)}")


if __name__ == "__main__":
    main()
