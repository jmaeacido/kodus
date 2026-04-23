# KODUS ERD Render Notes

## Source

- Input diagram: `docs/KODUS_ERD.mmd`
- Mermaid CLI: `.tmp-mermaid\node_modules\.bin\mmdc.cmd`
- Node runtime: `C:\laragon\bin\nodejs\node-v22`
- Browser executable: `C:\Program Files\Google\Chrome\Application\chrome.exe`

## Supporting Files

- Mermaid config: `docs/mermaid-render-config.json`
- Puppeteer config: `docs/puppeteer-config.json`
- Print stylesheet: `docs/mermaid-print.css`

## Commands Used

```powershell
$env:PATH='C:\laragon\bin\nodejs\node-v22;' + $env:PATH

& '.tmp-mermaid\node_modules\.bin\mmdc.cmd' `
  -i 'docs\KODUS_ERD.mmd' `
  -o 'docs\KODUS_ERD.svg' `
  -c 'docs\mermaid-render-config.json' `
  -C 'docs\mermaid-print.css' `
  -p 'docs\puppeteer-config.json' `
  -w 2400 -H 1800 -s 2 -b white -q

& '.tmp-mermaid\node_modules\.bin\mmdc.cmd' `
  -i 'docs\KODUS_ERD.mmd' `
  -o 'docs\KODUS_ERD.png' `
  -c 'docs\mermaid-render-config.json' `
  -C 'docs\mermaid-print.css' `
  -p 'docs\puppeteer-config.json' `
  -w 2400 -H 1800 -s 2 -b white -q

& '.tmp-mermaid\node_modules\.bin\mmdc.cmd' `
  -i 'docs\KODUS_ERD.mmd' `
  -o 'docs\KODUS_ERD.pdf' `
  -c 'docs\mermaid-render-config.json' `
  -C 'docs\mermaid-print.css' `
  -p 'docs\puppeteer-config.json' `
  -w 2400 -H 1800 -s 2 -f -q
```

## Render Settings

- Width: `2400`
- Height: `1800`
- Scale: `2`
- Background: `white`
- PDF fit mode: enabled with `-f`
- Layout/content changes to `docs/KODUS_ERD.mmd`: none

## Outputs

- `docs/KODUS_ERD.svg`
- `docs/KODUS_ERD.png`
- `docs/KODUS_ERD.pdf`
