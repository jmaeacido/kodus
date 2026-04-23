from pathlib import Path

from PIL import Image, ImageDraw, ImageFont
from reportlab.lib.utils import ImageReader
from reportlab.pdfgen import canvas


ROOT = Path(__file__).resolve().parent
PNG_PATH = ROOT / "KODUS_Use_Case.png"
PDF_PATH = ROOT / "KODUS_Use_Case.pdf"
FONT_DIR = Path(r"C:\Windows\Fonts")

WIDTH = 2200
HEIGHT = 3600
CENTER_X = WIDTH // 2
ELLIPSE_WIDTH = 560
ELLIPSE_HEIGHT = 92
START_Y = 270
ROW_STEP_Y = 150
COLUMN_GAP = 640


def load_font(name: str, size: int):
    try:
        return ImageFont.truetype(str(FONT_DIR / name), size)
    except OSError:
        return ImageFont.load_default()


TITLE_FONT = load_font("arialbd.ttf", 52)
SUBTITLE_FONT = load_font("arial.ttf", 28)
NODE_FONT = load_font("arial.ttf", 27)
LABEL_FONT = load_font("arialbd.ttf", 28)


USE_CASES = [
    "Login",
    "Logout",
    "View Dashboard",
    "Manage Profile/Settings",
    "Enable/Disable 2FA",
    "Send/Verify 2FA Code",
    "Manage Users",
    "Restore Deleted Users",
    "Manage App Settings",
    "View Audit Logs",
    "Manage Notifications",
    "Send Contact Message",
    "Start User-to-User\nConversation",
    "View Inbox/Messages",
    "Reply to Messages",
    "View Implementation\nStatus",
    "Manage Implementation\nStatus",
    "View Baseline Targets",
    "Manage Baseline Targets",
    "View Program Activities",
    "Manage Program Activities",
    "View LAWA Summary",
    "View BINHI Summary",
    "View Project Location\nRecords",
    "View Project Map",
    "Manage Project Locations",
    "Generate/Export Reports",
    "View Incoming Document\nTracking",
    "Track Incoming Documents",
    "Edit Incoming Documents",
    "Forward Incoming\nDocuments",
    "View Outgoing Document\nTracking",
    "Track Outgoing Documents",
    "Edit Outgoing Documents",
    "View Payout Records",
    "Manage Payout Records",
]


ACCESS = {
    "Administrator": set(USE_CASES),
    "Administrative\nStaff": {
        "Login",
        "Logout",
        "View Dashboard",
        "Manage Profile/Settings",
        "Enable/Disable 2FA",
        "Send/Verify 2FA Code",
        "Manage Notifications",
        "Send Contact Message",
        "Start User-to-User\nConversation",
        "View Inbox/Messages",
        "Reply to Messages",
        "View Implementation\nStatus",
        "View Baseline Targets",
        "View Program Activities",
        "View LAWA Summary",
        "View BINHI Summary",
        "View Project Location\nRecords",
        "View Project Map",
        "Generate/Export Reports",
        "View Incoming Document\nTracking",
        "Track Incoming Documents",
        "Edit Incoming Documents",
        "Forward Incoming\nDocuments",
        "View Outgoing Document\nTracking",
        "Track Outgoing Documents",
        "Edit Outgoing Documents",
        "View Payout Records",
        "Manage Payout Records",
    },
    "Implementation\nEditor": {
        "Login",
        "Logout",
        "View Dashboard",
        "Manage Profile/Settings",
        "Enable/Disable 2FA",
        "Send/Verify 2FA Code",
        "Manage Notifications",
        "Send Contact Message",
        "Start User-to-User\nConversation",
        "View Inbox/Messages",
        "Reply to Messages",
        "View Implementation\nStatus",
        "Manage Implementation\nStatus",
        "View Baseline Targets",
        "Manage Baseline Targets",
        "View Program Activities",
        "Manage Program Activities",
        "View LAWA Summary",
        "View BINHI Summary",
        "View Project Location\nRecords",
        "View Project Map",
        "Manage Project Locations",
        "Generate/Export Reports",
        "View Incoming Document\nTracking",
        "View Outgoing Document\nTracking",
        "View Payout Records",
    },
    "User": {
        "Login",
        "Logout",
        "View Dashboard",
        "Manage Profile/Settings",
        "Enable/Disable 2FA",
        "Send/Verify 2FA Code",
        "Manage Notifications",
        "Send Contact Message",
        "Start User-to-User\nConversation",
        "View Inbox/Messages",
        "Reply to Messages",
        "View Implementation\nStatus",
        "View Baseline Targets",
        "View Program Activities",
        "View LAWA Summary",
        "View BINHI Summary",
        "View Project Location\nRecords",
        "View Project Map",
        "Generate/Export Reports",
    },
}


ACTORS = {
    "Administrator": {"x": 150, "y": 860, "label_y": 1170},
    "Administrative\nStaff": {"x": 150, "y": 2380, "label_y": 2750},
    "Implementation\nEditor": {"x": 2050, "y": 1050, "label_y": 1420},
    "User": {"x": 2050, "y": 2500, "label_y": 2860},
}


def line_anchor(actor_name: str):
    actor = ACTORS[actor_name]
    x = actor["x"]
    y = actor["y"]
    if x < CENTER_X:
        return (x + 90, y + 130)
    return (x - 90, y + 130)


def node_center(index: int):
    row = index // 2
    col = index % 2
    x = CENTER_X - COLUMN_GAP // 2 if col == 0 else CENTER_X + COLUMN_GAP // 2
    y = START_Y + row * ROW_STEP_Y
    return (x, y)


def node_bbox(index: int):
    cx, cy = node_center(index)
    return (
        cx - ELLIPSE_WIDTH // 2,
        cy - ELLIPSE_HEIGHT // 2,
        cx + ELLIPSE_WIDTH // 2,
        cy + ELLIPSE_HEIGHT // 2,
    )


def draw_centered_text(draw, xy, text, font, fill):
    x, y = xy
    bbox = draw.multiline_textbbox((0, 0), text, font=font, align="center", spacing=6)
    w = bbox[2] - bbox[0]
    h = bbox[3] - bbox[1]
    draw.multiline_text(
        (x - w / 2, y - h / 2),
        text,
        font=font,
        fill=fill,
        align="center",
        spacing=6,
    )


def draw_actor(draw, actor_name):
    actor = ACTORS[actor_name]
    x = actor["x"]
    y = actor["y"]
    line = "#1f2937"
    draw.ellipse((x - 30, y, x + 30, y + 60), outline=line, width=5)
    draw.line((x, y + 60, x, y + 165), fill=line, width=5)
    draw.line((x - 60, y + 100, x + 60, y + 100), fill=line, width=5)
    draw.line((x, y + 165, x - 58, y + 245), fill=line, width=5)
    draw.line((x, y + 165, x + 58, y + 245), fill=line, width=5)
    draw_centered_text(draw, (x + (120 if x < CENTER_X else -120), actor["label_y"]), actor_name, LABEL_FONT, line)


def build_diagram():
    image = Image.new("RGBA", (WIDTH, HEIGHT), (255, 255, 255, 0))
    draw = ImageDraw.Draw(image)

    title_color = "#0f4c8a"
    subtitle_color = "#5b6473"
    outline = "#1f2937"
    line_color = "#90a0b4"

    draw_centered_text(draw, (CENTER_X, 100), "KODUS Use Case Diagram", TITLE_FONT, title_color)
    draw_centered_text(
        draw,
        (CENTER_X, 165),
        "KliMalasakit Online Document Updating System",
        SUBTITLE_FONT,
        subtitle_color,
    )

    for actor_name, allowed in ACCESS.items():
        ax, ay = line_anchor(actor_name)
        for idx, use_case in enumerate(USE_CASES):
            if use_case not in allowed:
                continue
            left, top, right, bottom = node_bbox(idx)
            target_x = left if ACTORS[actor_name]["x"] < CENTER_X else right
            draw.line((ax, ay, target_x, (top + bottom) / 2), fill=line_color, width=2)

    for idx, use_case in enumerate(USE_CASES):
        bbox = node_bbox(idx)
        draw.ellipse(bbox, fill=(255, 255, 255, 245), outline=outline, width=3)
        draw_centered_text(draw, node_center(idx), use_case, NODE_FONT, outline)

    for actor_name in ACTORS:
        draw_actor(draw, actor_name)

    image.save(PNG_PATH)

    c = canvas.Canvas(str(PDF_PATH), pagesize=(WIDTH, HEIGHT))
    c.setTitle("KODUS Use Case Diagram")
    c.drawImage(ImageReader(str(PNG_PATH)), 0, 0, width=WIDTH, height=HEIGHT, mask="auto")
    c.showPage()
    c.save()


if __name__ == "__main__":
    build_diagram()
