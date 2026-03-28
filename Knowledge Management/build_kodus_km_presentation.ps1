$source = 'C:\laragon\www\kodus\Knowledge Management\MER Presentation.pptx'
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$output = "C:\laragon\www\kodus\Knowledge Management\KODUS Knowledge Management Presentation $timestamp.pptx"

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
        [bool] $Bold = $false
    )

    $shape = $Slide.Shapes.AddTextbox(1, $Left, $Top, $Width, $Height)
    $shape.TextFrame.TextRange.Text = $Text
    $shape.TextFrame.TextRange.Font.Size = $FontSize
    $shape.TextFrame.TextRange.Font.Bold = $(if ($Bold) { -1 } else { 0 })
    $shape.TextFrame.WordWrap = -1
    $shape.Fill.Visible = 0
    $shape.Line.Visible = 0
    return $shape
}

$ppt = New-Object -ComObject PowerPoint.Application
$presentation = $ppt.Presentations.Open($source)

$slide1 = $presentation.Slides.Item(1)
Set-ShapeText -Slide $slide1 -ShapeId 58 -Text 'KliMalasakit Online Document Updating System (KODUS)' -FontSize 26 -Bold $true
$subtitle1 = Add-Textbox -Slide $slide1 -Text "Knowledge Management Session`r`nA Good Practice in Digital Innovation for RRP-CFTW LAWA and BINHI" -Left 102 -Top 760 -Width 1100 -Height 120 -FontSize 18 -Bold $false
$subtitle1.TextFrame.TextRange.Font.Color.RGB = 16777215

$slide2 = $presentation.Slides.Item(2)
Set-ShapeText -Slide $slide2 -ShapeId 72 -Text 'Purpose of the Tool' -FontSize 24 -Bold $true
Set-ShapeText -Slide $slide2 -ShapeId 73 -Text 'KODUS streamlines document tracking, masterlist validation, deduplication, crossmatching, and disaggregated reporting for the implementation of RRP-CFTW under Projects LAWA and BINHI.' -FontSize 18
Set-ShapeText -Slide $slide2 -ShapeId 74 -Text 'Who is this for' -FontSize 24 -Bold $true
Set-ShapeText -Slide $slide2 -ShapeId 75 -Text 'Designed for authorized DSWD Field Office Caraga personnel, including DRMD and RRP-CFTW implementers, validation staff, report generators, and program decision-makers who manage beneficiary and implementation data.' -FontSize 18

$slide3 = $presentation.Slides.Item(3)
$title3 = Add-Textbox -Slide $slide3 -Text 'Context and Challenge' -Left 230 -Top 160 -Width 860 -Height 60 -FontSize 24 -Bold $true
$body3 = @"
Before KODUS:
• Manual spreadsheets and fragmented files slowed operations.
• Duplicate records and version conflicts weakened data integrity.
• Document tracking and masterlist validation were labor-intensive.
• Disaggregated reporting across municipalities took too much time.

What KODUS changed:
• Centralized, secure, web-based internal system
• Real-time visibility of document and beneficiary workflows
• Automated validation, deduplication, and crossmatching
• Standardized outputs for reporting and monitoring
"@
$text3 = Add-Textbox -Slide $slide3 -Text $body3 -Left 420 -Top 270 -Width 930 -Height 560 -FontSize 18

$slide4 = $presentation.Slides.Item(4)
Set-ShapeText -Slide $slide4 -ShapeId 99 -Text 'Core Functionalities' -FontSize 24 -Bold $true
$body4 = @"
• Track document flow across program phases
• Manage and validate the Masterlist of Eligible Partner-Beneficiaries
• Run deduplication and crossmatching:
   - KODUS Database vs File
   - File vs File
• Generate sex-, age-, sector-, and barangay-disaggregated reports
• Monitor implementation status, partner beneficiaries, and compliance outputs
"@
$text4 = Add-Textbox -Slide $slide4 -Text $body4 -Left 215 -Top 365 -Width 1320 -Height 250 -FontSize 19

$slide5 = $presentation.Slides.Item(5)
Set-ShapeText -Slide $slide5 -ShapeId 112 -Text 'Pre-Implementation Phase' -FontSize 24 -Bold $true
$body5 = @"
• RRP-CFTW operations relied on manual spreadsheets and scattered trackers.
• Field staff spent significant time validating masterlists and reconciling records.
• Delays and inconsistencies affected reporting across multiple municipalities.
• DSWD FO Caraga identified the need for a secure, centralized, and automated internal platform.
"@
$text5 = Add-Textbox -Slide $slide5 -Text $body5 -Left 180 -Top 345 -Width 1260 -Height 200 -FontSize 18
$footer5 = Add-Textbox -Slide $slide5 -Text 'KODUS was conceptualized to reduce operational bottlenecks while protecting sensitive beneficiary data.' -Left 500 -Top 585 -Width 1080 -Height 170 -FontSize 18

$slide6 = $presentation.Slides.Item(6)
Set-ShapeText -Slide $slide6 -ShapeId 125 -Text 'Implementation Phase' -FontSize 24 -Bold $true
$body6 = @"
• Modules were configured to match LAWA and BINHI workflows.
• Authorized staff were oriented on system navigation and data protocols.
• Deduplication and crossmatching tools were rolled out to improve eligibility screening.
• Report filters automated demographic and sectoral summaries.
• Staff gained faster validation, stronger confidence in data, and quicker report turnaround.
"@
$text6 = Add-Textbox -Slide $slide6 -Text $body6 -Left 190 -Top 330 -Width 1260 -Height 215 -FontSize 18
$footer6 = Add-Textbox -Slide $slide6 -Text 'KODUS supported real-time tracking, standardized outputs, and more efficient program monitoring.' -Left 500 -Top 570 -Width 1040 -Height 150 -FontSize 18

$slide7 = $presentation.Slides.Item(7)
Set-ShapeText -Slide $slide7 -ShapeId 137 -Text 'Post-Implementation Enhancements' -FontSize 24 -Bold $true
$body7 = @"
• Added more reporting templates and geographic breakdowns
• Improved user interface and visual cues for field-level users
• Introduced audit logs and data integrity checks
• Continued refining features through user feedback
• Kept the platform responsive to emerging operational needs
"@
$text7 = Add-Textbox -Slide $slide7 -Text $body7 -Left 315 -Top 400 -Width 1180 -Height 230 -FontSize 19

$slide8 = $presentation.Slides.Item(8)
Set-ShapeText -Slide $slide8 -ShapeId 150 -Text 'Results and Impact' -FontSize 24 -Bold $true
$body8a = @"
Quantitative gains:
• Masterlist validation: 2-3 days to less than 1 day
• Document tracking accuracy: ~80% to 98-100%
• Beneficiary duplication rate: ~12% to less than 2%
• Disaggregated reports: 1-2 hours to less than 15 minutes
"@
$body8b = @"
Qualitative gains:
• Reduced manual workload for staff
• Faster coordination across program phases
• Better evidence-based planning for sectors and vulnerable groups
• Stronger internal reporting consistency and data integrity
"@
$text8a = Add-Textbox -Slide $slide8 -Text $body8a -Left 170 -Top 355 -Width 1160 -Height 180 -FontSize 18
$text8b = Add-Textbox -Slide $slide8 -Text $body8b -Left 250 -Top 605 -Width 1320 -Height 200 -FontSize 18

$slide9 = $presentation.Slides.Item(9)
Set-ShapeText -Slide $slide9 -ShapeId 163 -Text 'Lessons Learned and Replication' -FontSize 24 -Bold $true
$body9a = @"
Lessons learned:
• Orientation drives adoption.
• Restricted access strengthens accountability and data security.
• User feedback improves system functionality.
• Automation reduces reconciliation work and speeds up reporting.
"@
$body9b = @"
Replication considerations:
• KODUS is replicable across DSWD offices with similar workflows.
• Success requires technical stewardship, secure hosting, and documentation.
• Recommended next steps: readiness assessment, pilot rollout, feedback loop, and scaling documentation.
"@
$text9a = Add-Textbox -Slide $slide9 -Text $body9a -Left 280 -Top 285 -Width 1200 -Height 260 -FontSize 17
$text9b = Add-Textbox -Slide $slide9 -Text $body9b -Left 360 -Top 675 -Width 1180 -Height 240 -FontSize 17

$slide10 = $presentation.Slides.Item(10)
Set-ShapeText -Slide $slide10 -ShapeId 170 -Text 'THANK YOU!' -FontSize 28 -Bold $true
Set-ShapeText -Slide $slide10 -ShapeId 173 -Text 'Questions and Discussion' -FontSize 22 -Bold $false
$footer10 = Add-Textbox -Slide $slide10 -Text "Prepared by: John Mark Agustin E. Acido`r`nProject Development Officer I | RRP-CFTW Budget Focal`r`nKODUS: http://crg-co1-23-0028/kodus" -Left 118 -Top 735 -Width 900 -Height 130 -FontSize 16
$footer10.TextFrame.TextRange.Font.Color.RGB = 16777215

$presentation.SaveAs($output)
$presentation.Close()
$ppt.Quit()

Write-Output "Created: $output"
