$ppt = New-Object -ComObject PowerPoint.Application
$path = (Resolve-Path 'C:\laragon\www\kodus\Knowledge Management\KODUS Knowledge Management Presentation 20260322-164535.pptx').Path
$pres = $ppt.Presentations.Open($path)
foreach ($slide in $pres.Slides) {
  Write-Output ("--- Slide {0}: {1} shapes ---" -f $slide.SlideIndex, $slide.Shapes.Count)
  foreach ($shape in $slide.Shapes) {
    $text = ''
    if ($shape.HasTextFrame -eq -1 -and $shape.TextFrame.HasText -eq -1) {
      $text = $shape.TextFrame.TextRange.Text -replace "`r", ' ' -replace "`n", ' | '
    }
    $ph = '-'
    try { $ph = $shape.PlaceholderFormat.Type } catch {}
    Write-Output ("[{0}] Name='{1}' Type={2} Placeholder={3} Left={4} Top={5} Width={6} Height={7} Text='{8}'" -f $shape.Id, $shape.Name, $shape.Type, $ph, [int]$shape.Left, [int]$shape.Top, [int]$shape.Width, [int]$shape.Height, $text)
  }
}
$pres.Close()
$ppt.Quit()
