const fs = require('fs');
const { Document, Packer, Paragraph, TextRun, AlignmentType,
        HeadingLevel, Header, Footer, PageNumber } = require('docx');

const jsonPath = process.argv[2];
if (!jsonPath) {
    console.error('Usage: node render-docx.js <data.json>');
    process.exit(1);
}

const data = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
const { profile, sender, content, sections, output_path } = data;

function buildChildren() {
    const children = [];

    // Recipient block
    if (profile.recipient) {
        children.push(
            new Paragraph({
                alignment: AlignmentType.RIGHT,
                spacing: { after: 0 },
                children: [new TextRun({ text: profile.recipient.title || '', bold: true, size: 24, font: 'Arial' })],
            }),
            new Paragraph({
                alignment: AlignmentType.RIGHT,
                spacing: { after: 200 },
                children: [new TextRun({ text: profile.recipient.address || '', size: 22, font: 'Arial' })],
            })
        );
    }

    // Sender block
    children.push(
        new Paragraph({ spacing: { after: 0 }, children: [new TextRun({ text: sender.name, bold: true, size: 24, font: 'Arial' })] }),
        new Paragraph({ spacing: { after: 0 }, children: [new TextRun({ text: sender.address, size: 22, font: 'Arial' })] }),
        new Paragraph({ spacing: { after: 0 }, children: [new TextRun({ text: `OIB: ${sender.oib}`, size: 22, font: 'Arial' })] }),
        new Paragraph({ spacing: { after: 0 }, children: [new TextRun({ text: `E-mail: ${sender.email}`, size: 22, font: 'Arial' })] }),
        new Paragraph({ spacing: { after: 400 }, children: [new TextRun({ text: `Tel: ${sender.phone}`, size: 22, font: 'Arial' })] }),
    );

    // Title
    children.push(
        new Paragraph({
            heading: HeadingLevel.HEADING_1,
            alignment: AlignmentType.CENTER,
            spacing: { before: 400, after: 400 },
            children: [new TextRun({ text: profile.name.toUpperCase(), bold: true, size: 28, font: 'Arial' })],
        })
    );

    // Case reference
    if (data.case && data.case.case_number) {
        children.push(
            new Paragraph({
                alignment: AlignmentType.CENTER,
                spacing: { after: 400 },
                children: [new TextRun({
                    text: `Predmet: ${data.case.case_number}`,
                    bold: true, italics: true, size: 24, font: 'Arial'
                })],
            })
        );
    }

    // Content - split by sections or by paragraphs
    if (sections && sections.length > 0) {
        for (const section of sections) {
            // Section heading
            children.push(
                new Paragraph({
                    heading: HeadingLevel.HEADING_2,
                    spacing: { before: 300, after: 200 },
                    children: [new TextRun({ text: section.title, bold: true, size: 26, font: 'Arial' })],
                })
            );

            // Section content - split into paragraphs
            const paragraphs = (section.content || '').split('\n').filter(p => p.trim());
            for (const para of paragraphs) {
                children.push(
                    new Paragraph({
                        spacing: { after: 120 },
                        children: [new TextRun({ text: para.trim(), size: 24, font: 'Arial' })],
                    })
                );
            }
        }
    } else {
        // Fallback: use raw content
        const paragraphs = content.split('\n').filter(p => p.trim());
        for (const para of paragraphs) {
            children.push(
                new Paragraph({
                    spacing: { after: 120 },
                    children: [new TextRun({ text: para.trim(), size: 24, font: 'Arial' })],
                })
            );
        }
    }

    // Signature block
    children.push(
        new Paragraph({ spacing: { before: 600 }, children: [] }),
        new Paragraph({
            alignment: AlignmentType.RIGHT,
            spacing: { after: 0 },
            children: [new TextRun({ text: 'S postovanjem,', size: 24, font: 'Arial' })],
        }),
        new Paragraph({ spacing: { after: 400 }, children: [] }),
        new Paragraph({
            alignment: AlignmentType.RIGHT,
            children: [new TextRun({ text: `_______________________`, size: 24, font: 'Arial' })],
        }),
        new Paragraph({
            alignment: AlignmentType.RIGHT,
            children: [new TextRun({ text: sender.name, bold: true, size: 24, font: 'Arial' })],
        }),
    );

    return children;
}

const doc = new Document({
    styles: {
        default: { document: { run: { font: 'Arial', size: 24 } } },
        paragraphStyles: [
            { id: 'Heading1', name: 'Heading 1', basedOn: 'Normal', next: 'Normal', quickFormat: true,
              run: { size: 28, bold: true, font: 'Arial' },
              paragraph: { spacing: { before: 240, after: 240 }, outlineLevel: 0 } },
            { id: 'Heading2', name: 'Heading 2', basedOn: 'Normal', next: 'Normal', quickFormat: true,
              run: { size: 26, bold: true, font: 'Arial' },
              paragraph: { spacing: { before: 180, after: 180 }, outlineLevel: 1 } },
        ]
    },
    sections: [{
        properties: {
            page: {
                size: { width: 11906, height: 16838 }, // A4
                margin: { top: 1440, right: 1440, bottom: 1440, left: 1440 },
            }
        },
        headers: {
            default: new Header({
                children: [new Paragraph({
                    alignment: AlignmentType.RIGHT,
                    children: [new TextRun({ text: data.case?.case_number || '', size: 18, font: 'Arial', color: '888888' })],
                })],
            }),
        },
        footers: {
            default: new Footer({
                children: [new Paragraph({
                    alignment: AlignmentType.CENTER,
                    children: [
                        new TextRun({ text: 'Stranica ', size: 18, font: 'Arial', color: '888888' }),
                        new TextRun({ children: [PageNumber.CURRENT], size: 18, font: 'Arial', color: '888888' }),
                    ],
                })],
            }),
        },
        children: buildChildren(),
    }],
});

Packer.toBuffer(doc).then(buffer => {
    fs.writeFileSync(output_path, buffer);
    console.log(`OK: ${output_path}`);
}).catch(err => {
    console.error(`FAIL: ${err.message}`);
    process.exit(1);
});
