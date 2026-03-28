$source = 'C:\laragon\www\kodus\Knowledge Management\MER Presentation.pptx'
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$pptxOutput = "C:\laragon\www\kodus\Knowledge Management\KODUS KM Executive Presentation $timestamp.pptx"
$pdfOutput = "C:\laragon\www\kodus\Knowledge Management\KODUS KM Executive Presentation $timestamp.pdf"
$logoPath = 'C:\laragon\www\kodus\dist\img\kodus.png'

function Set-ShapeText {
    param(
        [Parameter(Mandatory = $true)] $Slide,
        [Parameter(Mandatory = $true)] [int] $ShapeId,
        [Parameter(Mandatory = $true)] [string] $Text,
        [int] $FontSize = 24,
        [bool] $Bold = $false
    )

    $shape = $Slide.Shapes | Where-Object { $_.Id -eq $ShapeId } | Select-Object -First 1
    if (-not $shape) {
        throw "Shape ID $ShapeId not found on slide $($Slide.SlideIndex)."
    }

    $shape.TextFrame.TextRange.Text = $Text
    $shape.TextFrame.TextRange.Font.Size = $FontSize
    $shape.TextFrame.TextRange.Font.Bold = $(if ($Bold) { -1 } else { 0 })
}

function Add-Textbox {
    param(
        [Parameter(Mandatory = $true)] $Slide,
        [Parameter(Mandatory = $true)] [string] $Text,
        [Parameter(Mandatory = $true)] [double] $Left,
        [Parameter(Mandatory = $true)] [double] $Top,
        [Parameter(Mandatory = $true)] [double] $Width,
        [Parameter(Mandatory = $true)] [double] $Height,
        [int] $FontSize = 20,
        [bool] $Bold = $false,
        [int] $RgbColor = 16777215
    )

    $shape = $Slide.Shapes.AddTextbox(1, $Left, $Top, $Width, $Height)
    $shape.TextFrame.TextRange.Text = $Text
    $shape.TextFrame.TextRange.Font.Size = $FontSize
    $shape.TextFrame.TextRange.Font.Bold = $(if ($Bold) { -1 } else { 0 })
    $shape.TextFrame.TextRange.Font.Color.RGB = $RgbColor
    $shape.TextFrame.WordWrap = -1
    $shape.Fill.Visible = 0
    $shape.Line.Visible = 0
    return $shape
}

function Add-Logo {
    param(
        [Parameter(Mandatory = $true)] $Slide,
        [double] $Left,
        [double] $Top,
        [double] $Width,
        [double] $Height
    )

    if (Test-Path $logoPath) {
        $picture = $Slide.Shapes.AddPicture($logoPath, 0, -1, $Left, $Top, $Width, $Height)
        return $picture
    }
}

$ppt = New-Object -ComObject PowerPoint.Application
$presentation = $ppt.Presentations.Open($source)

$slide1 = $presentation.Slides.Item(1)
Set-ShapeText -Slide $slide1 -ShapeId 58 -Text 'KliMalasakit Online Document Updating System (KODUS)' -FontSize 26 -Bold $true
$sub1 = Add-Textbox -Slide $slide1 -Text "Knowledge Management Session`r`nA digital good practice for RRP-CFTW LAWA and BINHI" -Left 102 -Top 760 -Width 1100 -Height 110 -FontSize 18
$null = Add-Logo -Slide $slide1 -Left 1420 -Top 140 -Width 260 -Height 260

$slide2 = $presentation.Slides.Item(2)
Set-ShapeText -Slide $slide2 -ShapeId 72 -Text 'Why KODUS Matters' -FontSize 24 -Bold $true
Set-ShapeText -Slide $slide2 -ShapeId 73 -Text 'KODUS brings document tracking, masterlist validation, deduplication, crossmatching, and reporting into one secure internal platform.' -FontSize 19
Set-ShapeText -Slide $slide2 -ShapeId 74 -Text 'Who Uses It' -FontSize 24 -Bold $true
Set-ShapeText -Slide $slide2 -ShapeId 75 -Text 'Authorized DSWD FO Caraga staff managing beneficiary data, implementation monitoring, validation, and reporting for LAWA and BINHI.' -FontSize 19

$slide3 = $presentation.Slides.Item(3)
$null = Add-Textbox -Slide $slide3 -Text 'Problem Before KODUS' -Left 250 -Top 170 -Width 700 -Height 50 -FontSize 24 -Bold $true -RgbColor 16777215
$body3a = " - Manual spreadsheets and fragmented files`r`n - Slow validation and reconciliation`r`n - Duplicate records and inconsistent versions`r`n - Delayed reporting across municipalities"
$null = Add-Textbox -Slide $slide3 -Text $body3a -Left 420 -Top 290 -Width 820 -Height 190 -FontSize 19 -RgbColor 16777215
$null = Add-Textbox -Slide $slide3 -Text 'What KODUS Changed' -Left 250 -Top 560 -Width 700 -Height 50 -FontSize 24 -Bold $true -RgbColor 16777215
$body3b = " - Centralized and secure web-based system`r`n - Real-time workflow visibility`r`n - Automated validation and matching`r`n - Faster standardized outputs"
$null = Add-Textbox -Slide $slide3 -Text $body3b -Left 420 -Top 650 -Width 820 -Height 180 -FontSize 19 -RgbColor 16777215

$slide4 = $presentation.Slides.Item(4)
Set-ShapeText -Slide $slide4 -ShapeId 99 -Text 'Core Functionalities' -FontSize 24 -Bold $true
$body4 = " - Document flow tracking`r`n - Masterlist management and validation`r`n - Deduplication and crossmatching`r`n - Disaggregated reporting by sex, age, sector, and barangay`r`n - Implementation and partner-beneficiary monitoring"
$null = Add-Textbox -Slide $slide4 -Text $body4 -Left 220 -Top 365 -Width 1280 -Height 220 -FontSize 19 -RgbColor 16777215

$slide5 = $presentation.Slides.Item(5)
Set-ShapeText -Slide $slide5 -ShapeId 112 -Text 'Pre-Implementation' -FontSize 24 -Bold $true
$body5 = " - Field staff relied on manual trackers and scattered files`r`n - Reconciliation and validation took too much time`r`n - Reporting quality varied across locations`r`n - A secure internal system became necessary"
$null = Add-Textbox -Slide $slide5 -Text $body5 -Left 180 -Top 345 -Width 1260 -Height 180 -FontSize 19 -RgbColor 16777215
$null = Add-Textbox -Slide $slide5 -Text 'KODUS was designed to remove these bottlenecks without compromising data privacy.' -Left 500 -Top 585 -Width 1080 -Height 120 -FontSize 18 -RgbColor 16777215

$slide6 = $presentation.Slides.Item(6)
Set-ShapeText -Slide $slide6 -ShapeId 125 -Text 'Implementation' -FontSize 24 -Bold $true
$body6 = " - Modules aligned to LAWA and BINHI workflows`r`n - Staff orientation supported adoption`r`n - Matching tools improved eligibility screening`r`n - Report filters reduced manual reporting work`r`n - Teams gained faster validation and stronger data confidence"
$null = Add-Textbox -Slide $slide6 -Text $body6 -Left 190 -Top 330 -Width 1260 -Height 215 -FontSize 19 -RgbColor 16777215
$null = Add-Textbox -Slide $slide6 -Text 'Result: quicker turnaround, cleaner data, and more reliable program monitoring.' -Left 500 -Top 570 -Width 1040 -Height 120 -FontSize 18 -RgbColor 16777215

$slide7 = $presentation.Slides.Item(7)
Set-ShapeText -Slide $slide7 -ShapeId 137 -Text 'Post-Implementation' -FontSize 24 -Bold $true
$body7 = " - Expanded report templates and location breakdowns`r`n - Improved user interface for field users`r`n - Audit logs and traceability features`r`n - Continuous enhancement based on user feedback"
$null = Add-Textbox -Slide $slide7 -Text $body7 -Left 315 -Top 400 -Width 1180 -Height 180 -FontSize 20 -RgbColor 16777215
$null = Add-Logo -Slide $slide7 -Left 1360 -Top 140 -Width 180 -Height 180

$slide8 = $presentation.Slides.Item(8)
Set-ShapeText -Slide $slide8 -ShapeId 150 -Text 'Results and Impact' -FontSize 24 -Bold $true
$body8a = "Key gains`r`n - Masterlist validation: 2-3 days to less than 1 day`r`n - Document tracking accuracy: about 80% to 98-100%`r`n - Duplication rate: about 12% to less than 2%`r`n - Report generation: 1-2 hours to less than 15 minutes"
$body8b = "Operational value`r`n - Less manual workload`r`n - Better coordination across phases`r`n - More evidence-based planning`r`n - Stronger internal reporting consistency"
$null = Add-Textbox -Slide $slide8 -Text $body8a -Left 170 -Top 350 -Width 1160 -Height 200 -FontSize 18 -RgbColor 16777215
$null = Add-Textbox -Slide $slide8 -Text $body8b -Left 250 -Top 610 -Width 1280 -Height 170 -FontSize 18 -RgbColor 16777215

$slide9 = $presentation.Slides.Item(9)
Set-ShapeText -Slide $slide9 -ShapeId 163 -Text 'Lessons and Replication' -FontSize 24 -Bold $true
$body9a = "What we learned`r`n - Orientation drives adoption`r`n - Restricted access protects sensitive data`r`n - Feedback improves the system`r`n - Automation reduces reconciliation work"
$body9b = "Why it can be replicated`r`n - Modular design fits similar DSWD workflows`r`n - Requires stewardship, secure hosting, and documentation`r`n - Best next step: pilot in interested offices, then scale"
$null = Add-Textbox -Slide $slide9 -Text $body9a -Left 280 -Top 285 -Width 1200 -Height 180 -FontSize 18 -RgbColor 16777215
$null = Add-Textbox -Slide $slide9 -Text $body9b -Left 360 -Top 675 -Width 1180 -Height 170 -FontSize 18 -RgbColor 16777215

$slide10 = $presentation.Slides.Item(10)
Set-ShapeText -Slide $slide10 -ShapeId 170 -Text 'THANK YOU!' -FontSize 28 -Bold $true
Set-ShapeText -Slide $slide10 -ShapeId 173 -Text 'Questions and Discussion' -FontSize 22
$null = Add-Textbox -Slide $slide10 -Text "Prepared by: John Mark Agustin E. Acido`r`nProject Development Officer I | RRP-CFTW Budget Focal`r`nKODUS: http://crg-co1-23-0028/kodus" -Left 118 -Top 735 -Width 900 -Height 120 -FontSize 16 -RgbColor 16777215
$null = Add-Logo -Slide $slide10 -Left 1290 -Top 320 -Width 260 -Height 260

$presentation.SaveAs($pptxOutput)
$presentation.SaveAs($pdfOutput, 32)
$presentation.Close()
$ppt.Quit()

Write-Output "Created PPTX: $pptxOutput"
Write-Output "Created PDF: $pdfOutput"
