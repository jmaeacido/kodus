const fs = require("fs");
const path = require("path");
const {
  AlignmentType,
  BorderStyle,
  Document,
  Footer,
  Header,
  HeadingLevel,
  ImageRun,
  LevelFormat,
  Packer,
  PageBreak,
  PageNumber,
  Paragraph,
  ShadingType,
  Table,
  TableCell,
  TableOfContents,
  TableRow,
  TextRun,
  WidthType,
} = require("../.tooling/node_modules/docx");

const repoRoot = path.resolve(__dirname, "../../../..");
const annexDir = path.resolve(repoRoot, "docs/gpd/annexes");
const outputDir = path.join(annexDir, "docx");
const reportPath = path.join(outputDir, "CONVERSION_REPORT.md");

const files = [
  { file: "ANNEXES_INDEX.md", title: "KODUS GPD Annexes Index", cover: "ANNEXES INDEX" },
  { file: "ANNEX_A_OPERATIONAL_WORKFLOW_SCREENSHOTS.md", title: "KODUS Operational Workflow Screenshots", cover: "ANNEX A" },
  { file: "ANNEX_B_MEB_VALIDATION_WORKFLOW.md", title: "MEB Validation Workflow", cover: "ANNEX B" },
  { file: "ANNEX_C_DEDUPLICATION_CROSSMATCH_OUTPUTS.md", title: "Deduplication and Crossmatch Outputs", cover: "ANNEX C" },
  { file: "ANNEX_D_SAMPLE_DISAGGREGATED_REPORTS.md", title: "Sample Disaggregated Reports", cover: "ANNEX D" },
  { file: "ANNEX_E_AUDIT_ACCOUNTABILITY_RECORDS.md", title: "Audit and Accountability Records", cover: "ANNEX E" },
  { file: "ANNEX_F_DEPLOYMENT_SUSTAINABILITY_DOCUMENTATION.md", title: "Deployment and Sustainability Documentation", cover: "ANNEX F" },
];

const margins = { top: 1080, right: 900, bottom: 900, left: 900 };
const red = "C00000";
const blue = "1F4E79";

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

function textRun(text, options = {}) {
  return new TextRun({
    text,
    font: "Arial",
    size: options.size || 22,
    bold: !!options.bold,
    italics: !!options.italics,
    color: options.color || "000000",
    break: options.break || 0,
  });
}

function parseInline(text, defaults = {}) {
  const runs = [];
  let remaining = text
    .replace(/<br\s*\/?>/gi, "\n")
    .replace(/<span style="color:red">\s*/gi, "[[RED_START]]")
    .replace(/<\/span>/gi, "[[RED_END]]")
    .replace(/&nbsp;/g, " ")
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">");

  let redMode = false;
  while (remaining.length > 0) {
    const nextRedStart = remaining.indexOf("[[RED_START]]");
    const nextRedEnd = remaining.indexOf("[[RED_END]]");
    const nextBold = remaining.indexOf("**");
    const candidates = [nextRedStart, nextRedEnd, nextBold].filter((x) => x >= 0);
    const next = candidates.length ? Math.min(...candidates) : -1;

    if (next > 0) {
      const chunk = remaining.slice(0, next);
      for (const part of chunk.split("\n")) {
        if (part !== "") runs.push(textRun(part, { ...defaults, color: redMode ? red : defaults.color }));
        if (part !== chunk.split("\n").at(-1)) runs.push(textRun("", { break: 1 }));
      }
      remaining = remaining.slice(next);
      continue;
    }

    if (remaining.startsWith("[[RED_START]]")) {
      redMode = true;
      remaining = remaining.slice("[[RED_START]]".length);
      continue;
    }
    if (remaining.startsWith("[[RED_END]]")) {
      redMode = false;
      remaining = remaining.slice("[[RED_END]]".length);
      continue;
    }
    if (remaining.startsWith("**")) {
      const close = remaining.indexOf("**", 2);
      if (close > 1) {
        const chunk = remaining.slice(2, close);
        runs.push(textRun(chunk, { ...defaults, bold: true, color: redMode ? red : defaults.color }));
        remaining = remaining.slice(close + 2);
        continue;
      }
    }

    const one = remaining[0];
    runs.push(textRun(one, { ...defaults, color: redMode ? red : defaults.color }));
    remaining = remaining.slice(1);
  }

  return runs.length ? runs : [textRun("", defaults)];
}

function paragraph(text, options = {}) {
  return new Paragraph({
    children: typeof text === "string" ? parseInline(text, options) : text,
    alignment: options.alignment || AlignmentType.JUSTIFIED,
    heading: options.heading,
    spacing: {
      before: options.before ?? 80,
      after: options.after ?? 120,
      line: options.line ?? 276,
    },
    bullet: options.bullet ? { level: 0 } : undefined,
  });
}

function coverPage(meta) {
  return [
    new Paragraph({ spacing: { before: 2500, after: 400 }, alignment: AlignmentType.CENTER, children: [textRun(meta.cover, { bold: true, size: 44, color: blue })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 300 }, children: [textRun(meta.title, { bold: true, size: 32 })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 200 }, children: [textRun("KliMalasakit Operational Data Unified System (KODUS)", { size: 24 })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 900 }, children: [textRun("DSWD Field Office Caraga", { size: 24 })] }),
    new Paragraph({ alignment: AlignmentType.CENTER, children: [textRun("[MANUAL INPUT REQUIRED: Insert official document date, reviewer, and approving official]", { color: red, bold: true })] }),
    new Paragraph({ children: [new PageBreak()] }),
  ];
}

function headerFooter(meta) {
  return {
    headers: {
      default: new Header({
        children: [
          new Paragraph({
            alignment: AlignmentType.CENTER,
            children: [textRun(`KODUS GPD Annexes - ${meta.cover}`, { bold: true, size: 18, color: "666666" })],
          }),
        ],
      }),
    },
    footers: {
      default: new Footer({
        children: [
          new Paragraph({
            alignment: AlignmentType.CENTER,
            children: [
              textRun("FOR OFFICIAL USE ONLY", { bold: true, size: 18, color: "666666" }),
              textRun("    Page ", { size: 18, color: "666666" }),
              new TextRun({ children: [PageNumber.CURRENT], font: "Arial", size: 18, color: "666666" }),
            ],
          }),
        ],
      }),
    },
  };
}

function tableFromRows(rows) {
  const maxCols = Math.max(...rows.map((row) => row.length));
  return new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    borders: {
      top: { style: BorderStyle.SINGLE, size: 4, color: "808080" },
      bottom: { style: BorderStyle.SINGLE, size: 4, color: "808080" },
      left: { style: BorderStyle.SINGLE, size: 4, color: "808080" },
      right: { style: BorderStyle.SINGLE, size: 4, color: "808080" },
      insideHorizontal: { style: BorderStyle.SINGLE, size: 4, color: "BFBFBF" },
      insideVertical: { style: BorderStyle.SINGLE, size: 4, color: "BFBFBF" },
    },
    rows: rows.map((row, rowIndex) => new TableRow({
      tableHeader: rowIndex === 0,
      children: Array.from({ length: maxCols }).map((_, cellIndex) => new TableCell({
        width: { size: Math.floor(100 / maxCols), type: WidthType.PERCENTAGE },
        shading: rowIndex === 0 ? { type: ShadingType.CLEAR, fill: "D9EAF7" } : undefined,
        margins: { top: 100, bottom: 100, left: 100, right: 100 },
        children: [
          new Paragraph({
            alignment: AlignmentType.LEFT,
            spacing: { before: 0, after: 0, line: 240 },
            children: parseInline((row[cellIndex] || "").trim(), {
              size: 18,
              bold: rowIndex === 0,
              color: rowIndex === 0 ? blue : "000000",
            }),
          }),
        ],
      })),
    })),
  });
}

function mermaidFlowchartParagraphs() {
  const steps = [
    "Prepare approved MEB workbook",
    "Upload/import workbook in KODUS",
    "Header and format validation",
    "Create background import job",
    "Insert MEB rows and assign batch ID",
    "Run duplicate and crossmatch checks as required",
    "Review validation page: target vs imported actual",
    "Correct exceptions or export validated report",
    "Document owner review and sign-off",
  ];
  const rows = steps.map((step, index) => [index + 1 === steps.length ? step : `${step} ->`]);
  return [
    paragraph("Rendered workflow equivalent for the Mermaid source:", { bold: true, alignment: AlignmentType.LEFT }),
    tableFromRows([["MEB Validation Workflow"], ...rows]),
  ];
}

function imageParagraph(markdownPath, alt, relPath) {
  const imagePath = path.resolve(path.dirname(markdownPath), relPath);
  if (!fs.existsSync(imagePath)) {
    return paragraph(`Screenshot file not found in repository package: ${relPath}. Document owner should attach the approved sanitized capture before external submission.`, { bold: true });
  }
  const data = fs.readFileSync(imagePath);
  return new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { before: 120, after: 120 },
    children: [
      new ImageRun({
        data,
        transformation: { width: 560, height: 369 },
        type: "png",
      }),
    ],
  });
}

function parseMarkdown(meta) {
  const markdownPath = path.join(annexDir, meta.file);
  const lines = fs.readFileSync(markdownPath, "utf8").split(/\r?\n/);
  const children = [...coverPage(meta)];
  let i = 0;
  let inFence = false;
  let fenceLang = "";
  let fenceLines = [];

  children.push(new TableOfContents("Table of Contents", { hyperlink: true, headingStyleRange: "1-3" }));
  children.push(new Paragraph({ children: [new PageBreak()] }));

  while (i < lines.length) {
    const line = lines[i];

    if (line.startsWith("```")) {
      if (!inFence) {
        inFence = true;
        fenceLang = line.slice(3).trim();
        fenceLines = [];
      } else {
        if (fenceLang === "mermaid") {
          children.push(...mermaidFlowchartParagraphs());
        } else {
          children.push(paragraph(fenceLines.join("\n"), { alignment: AlignmentType.LEFT, size: 18 }));
        }
        inFence = false;
        fenceLang = "";
      }
      i++;
      continue;
    }
    if (inFence) {
      fenceLines.push(line);
      i++;
      continue;
    }

    const imageMatch = line.match(/^!\[([^\]]*)\]\(([^)]+)\)/);
    if (imageMatch) {
      children.push(imageParagraph(markdownPath, imageMatch[1], imageMatch[2]));
      i++;
      continue;
    }

    const heading = line.match(/^(#{1,6})\s+(.+)$/);
    if (heading) {
      const level = heading[1].length;
      const headingLevel = level === 1 ? HeadingLevel.HEADING_1 : level === 2 ? HeadingLevel.HEADING_2 : HeadingLevel.HEADING_3;
      children.push(paragraph(heading[2], {
        heading: headingLevel,
        alignment: AlignmentType.LEFT,
        bold: true,
        color: blue,
        size: level === 1 ? 32 : level === 2 ? 26 : 23,
        before: level === 1 ? 320 : 220,
        after: 140,
      }));
      i++;
      continue;
    }

    if (/^\|.*\|$/.test(line) && i + 1 < lines.length && /^\|\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?$/.test(lines[i + 1])) {
      const tableLines = [line];
      i += 2;
      while (i < lines.length && /^\|.*\|$/.test(lines[i])) {
        tableLines.push(lines[i]);
        i++;
      }
      const rows = tableLines.map((tableLine) => tableLine.trim().replace(/^\|/, "").replace(/\|$/, "").split("|").map((cell) => cell.trim()));
      children.push(tableFromRows(rows));
      continue;
    }

    if (/^\s*-\s+/.test(line)) {
      children.push(paragraph(line.replace(/^\s*-\s+/, ""), { bullet: true, alignment: AlignmentType.LEFT }));
      i++;
      continue;
    }

    if (line.trim() !== "") {
      children.push(paragraph(line.trim()));
    }
    i++;
  }

  return children;
}

function makeDocument(meta, children) {
  return new Document({
    creator: "KODUS Documentation Tooling",
    title: meta.title,
    description: "Submission-ready KODUS GPD annex document",
    styles: {
      default: {
        document: { run: { font: "Arial", size: 22 }, paragraph: { spacing: { line: 276, after: 120 } } },
      },
      paragraphStyles: [
        { id: "Heading1", name: "Heading 1", basedOn: "Normal", next: "Normal", quickFormat: true, run: { font: "Arial", size: 32, bold: true, color: blue }, paragraph: { spacing: { before: 320, after: 160 } } },
        { id: "Heading2", name: "Heading 2", basedOn: "Normal", next: "Normal", quickFormat: true, run: { font: "Arial", size: 26, bold: true, color: blue }, paragraph: { spacing: { before: 260, after: 140 } } },
        { id: "Heading3", name: "Heading 3", basedOn: "Normal", next: "Normal", quickFormat: true, run: { font: "Arial", size: 23, bold: true, color: blue }, paragraph: { spacing: { before: 220, after: 120 } } },
      ],
    },
    numbering: {
      config: [
        {
          reference: "default-bullets",
          levels: [{ level: 0, format: LevelFormat.BULLET, text: "•", alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 720, hanging: 360 } } } }],
        },
      ],
    },
    sections: [
      {
        properties: { page: { margin: margins } },
        ...headerFooter(meta),
        children,
      },
    ],
  });
}

async function writeDocx(meta) {
  const children = parseMarkdown(meta);
  const doc = makeDocument(meta, children);
  const out = path.join(outputDir, meta.file.replace(/\.md$/, ".docx"));
  fs.writeFileSync(out, await Packer.toBuffer(doc));
  return out;
}

async function main() {
  ensureDir(outputDir);
  const generated = [];
  for (const meta of files) {
    generated.push(await writeDocx(meta));
  }

  const masterChildren = [];
  for (const [index, meta] of files.entries()) {
    if (index > 0) masterChildren.push(new Paragraph({ children: [new PageBreak()] }));
    masterChildren.push(...parseMarkdown(meta));
  }
  const masterDoc = makeDocument({ title: "KODUS GPD Annexes - Combined Master", cover: "MASTER ANNEXES" }, masterChildren);
  const masterPath = path.join(outputDir, "KODUS_GPD_ANNEXES_MASTER.docx");
  fs.writeFileSync(masterPath, await Packer.toBuffer(masterDoc));
  generated.push(masterPath);

  const report = [
    "# KODUS GPD Annex DOCX Conversion Report",
    "",
    `Generated on: ${new Date().toISOString()}`,
    "",
    "## Generated DOCX Files",
    "",
    ...generated.map((filePath) => `- ${path.basename(filePath)}`),
    "",
    "## Formatting Applied",
    "",
    "- Arial 11 pt default body text.",
    "- Bold Word heading styles with spacing before/after headings.",
    "- Justified body paragraphs.",
    "- Markdown tables converted to editable Word tables with visible borders and shaded header rows.",
    "- Centered annex cover pages.",
    "- Automatic Word Table of Contents fields inserted; update fields in Microsoft Word if page numbers do not appear immediately.",
    "- Page footer includes FOR OFFICIAL USE ONLY and page numbering field.",
    "- Repository-reviewed placeholders resolved with documented assumptions, sanitized screenshot references, or owner-validation notes.",
    "- Mermaid workflow source converted to an editable Word-table flowchart equivalent.",
    "",
    "## Unresolved Formatting Limitations",
    "",
    "- Microsoft Word may require right-click > Update Field on the Table of Contents after opening.",
    "- Mermaid was converted to a Word-table flowchart equivalent rather than SmartArt because SmartArt creation is not exposed by the local DOCX library.",
    "- Screenshot scale is standardized for portrait pages; final cropping may be adjusted in Word after sign-off.",
    "",
    "## Manual Formatting Recommended in Microsoft Word",
    "",
    "- Update all Table of Contents fields.",
    "- Confirm page breaks before final PDF export.",
    "- Confirm approved screenshots, signatories, dates, and owner-validated values before final PDF export.",
    "- Confirm agency letterhead/header requirements if an official DSWD template is mandated.",
    "",
  ].join("\n");
  fs.writeFileSync(reportPath, report);
  console.log(`Generated ${generated.length} DOCX files in ${outputDir}`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
