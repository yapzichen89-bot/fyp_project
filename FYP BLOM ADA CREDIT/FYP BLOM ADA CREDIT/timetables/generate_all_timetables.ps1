$times = @(
    '08:00-09:00','09:00-10:00','10:00-11:00','11:00-12:00','12:00-13:00',
    '13:00-14:00','14:00-15:00','15:00-16:00','16:00-17:00','17:00-18:00'
)
$days = @('ISNIN','SELASA','RABU','KHAMIS','JUMAAT')

function Get-DisplayText($code, $lecturer, $room) {
    $parts = @()
    if ($code) { $parts += $code }
    if ($lecturer) { $parts += "Lecturer: $lecturer" }
    if ($room -and $room -ne '-') { $parts += "Room: $room" }
    return ($parts -join '<br>')
}

function New-SubjectCard($code, $lecturer, $room, $color) {
    return @"
    <div class="subject $color">
      <div class="code">$code</div>
      <div class="meta">$(Get-DisplayText $code $lecturer $room)</div>
    </div>
"@
}

function Test-Overlap([string]$s1, [string]$e1, [string]$s2, [string]$e2) {
    $start1 = [int]($s1.Split(':')[0]) * 60 + [int]($s1.Split(':')[1])
    $end1   = [int]($e1.Split(':')[0]) * 60 + [int]($e1.Split(':')[1])
    $start2 = [int]($s2.Split(':')[0]) * 60 + [int]($s2.Split(':')[1])
    $end2   = [int]($e2.Split(':')[0]) * 60 + [int]($e2.Split(':')[1])
    return ($start1 -lt $end2) -and ($start2 -lt $end1)
}

function New-TimetableHtml($program, $institution, $session, $advisor, $schedule) {
    $assignment = @{}
    foreach ($day in $days) {
        foreach ($item in $schedule[$day]) {
            foreach ($slot in $times) {
                $slotStart = $slot.Split('-')[0]
                $slotEnd = $slot.Split('-')[1]
                if (Test-Overlap $item.start $item.end $slotStart $slotEnd) {
                    $assignment["$day|$slot"] = $item
                }
            }
        }
    }

    $colorIndex = 0
    $colorMap = @{}
    foreach ($day in $days) {
        foreach ($item in $schedule[$day]) {
            $key = "$($item.code)|$($item.room)"
            if (-not $colorMap.ContainsKey($key)) {
                $colors = @('blue','green','peach','purple','yellow','pink','mint','orange','lavender','coral')
                $colorMap[$key] = $colors[$colorIndex % $colors.Count]
                $colorIndex++
            }
        }
    }

    $rows = @()
    foreach ($slot in $times) {
        $row = @("<tr>", "<td class='time-col'>$slot</td>")
        foreach ($day in $days) {
            $key = "$day|$slot"
            if ($assignment.ContainsKey($key)) {
                $item = $assignment[$key]
                $color = $colorMap["$($item.code)|$($item.room)"]
                $row += "<td class='slot'>$(New-SubjectCard $item.code $item.lecturer $item.room $color)</td>"
            } else {
                $row += "<td class='slot'></td>"
            }
        }
        $row += "</tr>"
        $rows += $row -join ''
    }

    $html = @"
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>$program Timetable</title>
  <style>
    :root {
      --navy: #0a2240;
      --navy-2: #143d6b;
      --bg: #edf3f9;
      --panel: #f9fafc;
      --line: #d9e0eb;
      --text: #1e2a39;
      --muted: #53627b;
      --blue: #dfeeff;
      --green: #dff7e8;
      --peach: #ffe7d4;
      --purple: #e8e1ff;
      --yellow: #fff0bf;
      --pink: #ffdfe8;
      --mint: #dffaf5;
      --orange: #ffe6bf;
      --lavender: #efe5ff;
      --coral: #ffd9d1;
      --shadow: 0 10px 22px rgba(10, 34, 64, 0.12);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      background: var(--bg);
      color: var(--text);
    }
    .page { width: min(1280px, calc(100% - 26px)); margin: 24px auto; }
    .topbar {
      background: linear-gradient(90deg, var(--navy), var(--navy-2));
      color: #fff;
      border-radius: 12px 12px 0 0;
      padding: 18px 22px;
      box-shadow: var(--shadow);
    }
    .title { margin: 0; font-size: clamp(1.5rem, 2vw, 2.3rem); letter-spacing: 0.02em; font-weight: 700; }
    .meta-grid {
      margin-top: 14px;
      display: grid;
      grid-template-columns: repeat(4, minmax(180px, 1fr));
      gap: 10px;
    }
    .meta-box {
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 10px;
      padding: 10px 12px;
      line-height: 1.4;
      font-size: 0.9rem;
    }
    .meta-label {
      display: block;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      opacity: 0.8;
      margin-bottom: 4px;
    }
    .table-wrap {
      background: var(--panel);
      border: 1px solid var(--line);
      border-top: none;
      border-radius: 0 0 12px 12px;
      overflow: hidden;
      box-shadow: var(--shadow);
    }
    table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }
    th, td {
      border: 1px solid var(--line);
      padding: 0;
      text-align: center;
      vertical-align: top;
      background: #fff;
    }
    thead th {
      background: #f1f5fb;
      color: var(--text);
      font-size: 0.98rem;
      font-weight: 700;
      padding: 12px 8px;
    }
    .time-col {
      width: 120px;
      background: #f3f6fa;
      font-weight: 700;
      color: var(--text);
    }
    .slot { height: 88px; padding: 7px; }
    .subject {
      height: 100%;
      border: 1px solid rgba(0,0,0,0.04);
      border-radius: 8px;
      padding: 8px 8px 7px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      text-align: left;
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.4);
    }
    .code {
      font-weight: 700;
      font-size: 0.8rem;
      line-height: 1.2;
      margin-bottom: 4px;
      color: #1a2c42;
    }
    .meta {
      font-size: 0.68rem;
      line-height: 1.35;
      color: #2f3d52;
    }
    .blue { background: var(--blue); }
    .green { background: var(--green); }
    .peach { background: var(--peach); }
    .purple { background: var(--purple); }
    .yellow { background: var(--yellow); }
    .pink { background: var(--pink); }
    .mint { background: var(--mint); }
    .orange { background: var(--orange); }
    .lavender { background: var(--lavender); }
    .coral { background: var(--coral); }
    @media (max-width: 860px) {
      .meta-grid { grid-template-columns: 1fr 1fr; }
      .table-wrap { overflow-x: auto; }
      table { min-width: 920px; }
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="topbar">
      <h1 class="title">$institution</h1>
      <div class="meta-grid">
        <div class="meta-box"><span class="meta-label">Program</span>$program</div>
        <div class="meta-box"><span class="meta-label">Session</span>$session</div>
        <div class="meta-box"><span class="meta-label">Academic Advisor</span>$advisor</div>
        <div class="meta-box"><span class="meta-label">Class</span>$program</div>
      </div>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th class="time-col">Time</th>
            <th>ISNIN</th>
            <th>SELASA</th>
            <th>RABU</th>
            <th>KHAMIS</th>
            <th>JUMAAT</th>
          </tr>
        </thead>
        <tbody>
          $($rows -join '')
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
"@
    return $html
}

$programs = @(
    @{ file='semester1_s3.html'; institution='POLITEKNIK KUCHING SARAWAK'; program='DIT1-S3'; session='SESI 1 2026/2027'; advisor='SYAZWANI BINTI ABDUL KADIR'; schedule=@{
        'ISNIN'=@(@{start='08:00'; end='10:00'; code='MPU22153'; lecturer='ASZO'; room='BPJPA2* (A)'} , @{start='13:00'; end='15:00'; code='DFC10273'; lecturer='FHER'; room='HYP'} , @{start='15:00'; end='17:00'; code='DFC10353'; lecturer='FSBM'; room='HYP'} );
        'SELASA'=@(@{start='08:00'; end='10:00'; code='DBM10143'; lecturer='BLKM'; room='TECC* (F)'} , @{start='10:00'; end='12:00'; code='DFC10353'; lecturer='FSBM'; room='MML'} , @{start='15:00'; end='17:00'; code='DFC10263'; lecturer='FDBM'; room='DCN'} );
        'RABU'=@(@{start='10:00'; end='12:00'; code='DBM10143'; lecturer='BLKM'; room='BK32# (C)'} , @{start='15:00'; end='17:00'; code='MPU24XX1'; lecturer='PROGRAM'; room='DIT1-S3'} );
        'KHAMIS'=@(@{start='08:00'; end='10:00'; code='MPU22153'; lecturer='ASZO'; room='BPJPA2* (A)'} , @{start='12:00'; end='14:00'; code='DFC10273'; lecturer='FHER'; room='PGL2'} , @{start='16:00'; end='17:00'; code='PA'; lecturer='FSYA'; room='-'} );
        'JUMAAT'=@(@{start='08:00'; end='10:00'; code='DFC10263'; lecturer='FDBM'; room='PGL2'} );
    } },
    @{ file='semester2_s1.html'; institution='POLITEKNIK KUCHING SARAWAK'; program='DIT2-S1'; session='SESI 1 2026/2027'; advisor='AZHAR BIN ABD HAMID'; schedule=@{
        'ISNIN'=@(@{start='12:00'; end='14:00'; code='DFC20283'; lecturer='FFWA'; room='CSD'} , @{start='14:00'; end='17:00'; code='DFC20293'; lecturer='FMNR'; room='CSD'} );
        'SELASA'=@(@{start='08:00'; end='10:00'; code='DFC20283'; lecturer='FFWA'; room='CSD'} , @{start='10:00'; end='13:00'; code='DFK20013'; lecturer='FNOM'; room='CSD'} , @{start='13:00'; end='14:00'; code='MPU23162 / MPU23272'; lecturer='ANMN / ADAJ'; room='BK30* (A) / BPJPA3* (A)'} );
        'RABU'=@(@{start='08:00'; end='10:00'; code='DFC20313'; lecturer='FNBM'; room='CSD'} , @{start='10:00'; end='12:00'; code='MPU23162 / MPU23272'; lecturer='ANMN / ADAJ'; room='BK30* (A) / BK33 (J)'} , @{start='13:00'; end='15:00'; code='DBM20153'; lecturer='BTES'; room='BK4 (B)'} , @{start='15:00'; end='17:00'; code='MPU24XX1'; lecturer='PROGRAM'; room='DIT2-S1'} );
        'KHAMIS'=@(@{start='08:00'; end='10:00'; code='DFC20293'; lecturer='FMNR'; room='CSD'} , @{start='16:00'; end='17:00'; code='PA'; lecturer='FAAH'; room='-'} );
        'JUMAAT'=@(@{start='08:00'; end='10:00'; code='DBM20153'; lecturer='BTES'; room='BK41 (P)'} , @{start='10:00'; end='13:00'; code='DFK20013'; lecturer='FNOM'; room='APL'} , @{start='15:00'; end='17:00'; code='DFC20313'; lecturer='FNBM'; room='CSD'} );
    } },
    @{ file='semester2_s3.html'; institution='POLITEKNIK KUCHING SARAWAK'; program='DIT2-S3'; session='SESI 1 2026/2027'; advisor='MOHAMAD NIZAM B RAHADI'; schedule=@{
        'ISNIN'=@(@{start='08:00'; end='10:00'; code='DFC20293'; lecturer='FSYA'; room='APL'} , @{start='14:00'; end='17:00'; code='DFK20013'; lecturer='FNOM'; room='APL'} );
        'SELASA'=@(@{start='08:00'; end='10:00'; code='DFC20283'; lecturer='FWAH'; room='APL'} , @{start='10:00'; end='13:00'; code='DFC20313'; lecturer='FMNR'; room='APL'} , @{start='13:00'; end='14:00'; code='MPU23162 / MPU23272'; lecturer='ANMN / ADAJ'; room='BK30* (A) / BPJPA3* (A)'} );
        'RABU'=@(@{start='10:00'; end='12:00'; code='MPU23162 / MPU23272'; lecturer='ANMN / ADAJ'; room='BK30* (A) / BK33 (J)'} , @{start='13:00'; end='15:00'; code='DBM20153'; lecturer='BTES'; room='BK4 (B)'} , @{start='15:00'; end='17:00'; code='MPU24XX1'; lecturer='PROGRAM'; room='DIT2-S3'} );
        'KHAMIS'=@(@{start='08:00'; end='11:00'; code='DFK20013'; lecturer='FNOM'; room='APL'} , @{start='12:00'; end='14:00'; code='DFC20313'; lecturer='FMNR'; room='APL'} , @{start='16:00'; end='17:00'; code='PA'; lecturer='FMNR'; room='-'} );
        'JUMAAT'=@(@{start='08:00'; end='10:00'; code='DBM20153'; lecturer='BTES'; room='BK41 (P)'} , @{start='10:00'; end='12:00'; code='DFC20283'; lecturer='FWAH'; room='CSD'} , @{start='14:00'; end='17:00'; code='DFC20293'; lecturer='FSYA'; room='APL'} );
    } },
    @{ file='semester3_s1.html'; institution='POLITEKNIK KUCHING SARAWAK'; program='DIT3-S1'; session='SESI 1 2026/2027'; advisor='ANDING NYUAK'; schedule=@{
        'ISNIN'=@(@{start='08:00'; end='10:00'; code='DFK30053'; lecturer='FNBD'; room='MML'} , @{start='10:00'; end='13:00'; code='DFC30333'; lecturer='FSBR'; room='MML'} );
        'SELASA'=@(@{start='08:00'; end='10:00'; code='DFK30023'; lecturer='FKEF'; room='-'} , @{start='12:00'; end='15:00'; code='DFK30033'; lecturer='FTSK'; room='TECC* (F)'} , @{start='15:00'; end='17:00'; code='DFC30333'; lecturer='FSBR'; room='MML'} );
        'RABU'=@(@{start='08:00'; end='11:00'; code='DFP30313'; lecturer='FAAH'; room='MML'} , @{start='13:00'; end='15:00'; code='DBM30263'; lecturer='BLYL'; room='DK2* (C)'} , @{start='15:00'; end='17:00'; code='DFK30033'; lecturer='FTSK'; room='MML'} );
        'KHAMIS'=@(@{start='08:00'; end='11:00'; code='DFK30023'; lecturer='FKEF'; room='MML'} , @{start='13:00'; end='15:00'; code='DBM30263'; lecturer='BLYL'; room='BK39 (J)'} , @{start='16:00'; end='17:00'; code='PA'; lecturer='FAAN'; room='HYP'} );
        'JUMAAT'=@(@{start='10:00'; end='13:00'; code='DFP30313'; lecturer='FAAH'; room='MML'} , @{start='14:00'; end='17:00'; code='DFK30053'; lecturer='FNBD'; room='MML'} );
    } },
    @{ file='semester3_s2.html'; institution='POLITEKNIK KUCHING SARAWAK'; program='DIT3-S2'; session='SESI 1 2026/2027'; advisor='KEDUNG FLETCHER'; schedule=@{
        'ISNIN'=@(@{start='08:00'; end='10:00'; code='DFP30313'; lecturer='FTSK'; room='HYP'} , @{start='10:00'; end='13:00'; code='DFK30053'; lecturer='FABM'; room='HYP'} );
        'SELASA'=@(@{start='08:00'; end='10:00'; code='DFC30333'; lecturer='FNBM'; room='HYP'} , @{start='14:00'; end='17:00'; code='DFK30033'; lecturer='FSBM'; room='HYP'} );
        'RABU'=@(@{start='08:00'; end='10:00'; code='DFK30033'; lecturer='FSBM'; room='-'} , @{start='10:00'; end='13:00'; code='DFK30053'; lecturer='FABM'; room='HYP'} , @{start='13:00'; end='15:00'; code='DBM30263'; lecturer='BLYL'; room='DK2* (C)'} , @{start='15:00'; end='17:00'; code='DFK30023'; lecturer='FNBZ'; room='HYP'} );
        'KHAMIS'=@(@{start='08:00'; end='11:00'; code='DFC30333'; lecturer='FNBM'; room='-'} , @{start='11:00'; end='14:00'; code='DFK30023'; lecturer='FNBZ'; room='HYP'} , @{start='16:00'; end='17:00'; code='PA'; lecturer='FKEF'; room='-'} );
        'JUMAAT'=@(@{start='10:00'; end='12:00'; code='DBM30263'; lecturer='BLYL'; room='BK6 (J)'} , @{start='13:00'; end='15:00'; code='DFP30313'; lecturer='FTSK'; room='HYP'} , @{start='15:00'; end='17:00'; code='DFP30313'; lecturer='FTSK'; room='HYP'} );
    } },
    @{ file='semester3_s3.html'; institution='POLITEKNIK KUCHING SARAWAK'; program='DIT3-S3'; session='SESI 1 2026/2027'; advisor='SITI FATIA BINTI MOHAMAD ALI'; schedule=@{
        'ISNIN'=@(@{start='10:00'; end='11:00'; code='DFN30533'; lecturer='FSFM'; room='NWL'} , @{start='11:00'; end='13:00'; code='DFN30533'; lecturer='FSFM'; room='NWL'} );
        'SELASA'=@(@{start='08:00'; end='10:00'; code='DBM30263'; lecturer='BLYL'; room='DK2* (C)'} , @{start='12:00'; end='15:00'; code='DFK30053'; lecturer='FCMC'; room='MML'} , @{start='15:00'; end='17:00'; code='DFK30023'; lecturer='FNYH'; room='TECC* (F)'} );
        'RABU'=@(@{start='08:00'; end='10:00'; code='DBM30263'; lecturer='BLYL'; room='BK3 (B)'} , @{start='10:00'; end='13:00'; code='DFK30033'; lecturer='FSBR'; room='PGL1'} , @{start='15:00'; end='17:00'; code='DFN30533'; lecturer='FSFM'; room='NWL'} );
        'KHAMIS'=@(@{start='08:00'; end='10:00'; code='DFK30033'; lecturer='FSBR'; room='-'} , @{start='10:00'; end='13:00'; code='DFK30023'; lecturer='FNYH'; room='PGL1'} , @{start='13:00'; end='15:00'; code='DFC30333'; lecturer='FNZH'; room='PGL1'} , @{start='16:00'; end='17:00'; code='PA'; lecturer='FSFM'; room='-'} );
        'JUMAAT'=@(@{start='10:00'; end='13:00'; code='DFK30053'; lecturer='FCMC'; room='PGL1'} , @{start='14:00'; end='17:00'; code='DFC30333'; lecturer='FNZH'; room='-'} );
    } },
    @{ file='semester3_s4.html'; institution='POLITEKNIK KUCHING SARAWAK'; program='DIT3-S4'; session='SESI 1 2026/2027'; advisor='SANJAY CHARLES ALBERT'; schedule=@{
        'ISNIN'=@(@{start='08:00'; end='11:00'; code='DFK30053'; lecturer='FDBM'; room='ITL'} , @{start='11:00'; end='14:00'; code='DFK30033'; lecturer='FSMY'; room='PGL2'} );
        'SELASA'=@(@{start='08:00'; end='10:00'; code='DBM30263'; lecturer='BLYL'; room='DK2* (C)'} , @{start='12:00'; end='14:00'; code='DFN30533'; lecturer='FSYA'; room='DCN'} , @{start='14:00'; end='15:00'; code='DFN30533'; lecturer='FSYA'; room='DCN'} , @{start='15:00'; end='17:00'; code='DFC30333'; lecturer='FNMP'; room='PGL1'} );
        'RABU'=@(@{start='08:00'; end='11:00'; code='DFC30333'; lecturer='FNMP'; room='PGL2'} , @{start='11:00'; end='14:00'; code='DFK30023'; lecturer='FAFO'; room='PGL2'} );
        'KHAMIS'=@(@{start='08:00'; end='10:00'; code='DBM30263'; lecturer='BLYL'; room='BK41 (P)'} , @{start='10:00'; end='13:00'; code='DFK30023'; lecturer='FAFO'; room='PGL2'} , @{start='13:00'; end='15:00'; code='DFN30533'; lecturer='FSYA'; room='NWL'} , @{start='16:00'; end='17:00'; code='PA'; lecturer='FSCA'; room='-'} );
        'JUMAAT'=@(@{start='10:00'; end='13:00'; code='DFK30033'; lecturer='FSMY'; room='-'} , @{start='14:00'; end='17:00'; code='DFK30053'; lecturer='FDBM'; room='PGL2'} );
    } },
    @{ file='semester4_s1.html'; institution='POLITEKNIK KUCHING SARAWAK'; program='DIT4-S1'; session='SESI 1 2026/2027'; advisor='AZIZAH BINTI OHAMZAH'; schedule=@{
        'ISNIN'=@(@{start='08:00'; end='10:00'; code='DFK40083'; lecturer='FJAG / FNAB'; room='DCN'} , @{start='10:00'; end='12:00'; code='DFK40073'; lecturer='FZHI'; room='CSD'} , @{start='14:00'; end='17:00'; code='DFP40453'; lecturer='FAAH'; room='PGL2'} );
        'SELASA'=@(@{start='08:00'; end='09:00'; code='DFP40453'; lecturer='FAAH'; room='PGL1'} , @{start='09:00'; end='10:00'; code='DFP30313'; lecturer='FNBZ'; room='PGL1'} , @{start='10:00'; end='12:00'; code='DFP30313'; lecturer='FNBZ'; room='PGL1'} , @{start='14:00'; end='17:00'; code='DFK40083'; lecturer='FJAG / FNAB'; room='NWL'} );
        'RABU'=@(@{start='08:00'; end='10:00'; code='DFP40443'; lecturer='FNOM'; room='APL'} , @{start='10:00'; end='12:00'; code='DFP40443'; lecturer='FNOM'; room='APL'} , @{start='12:00'; end='13:00'; code='DFC40343'; lecturer='FFWA'; room='-'} , @{start='14:00'; end='17:00'; code='DFK40073'; lecturer='FZHI'; room='APL'} );
        'KHAMIS'=@(@{start='08:00'; end='11:00'; code='DFP30313'; lecturer='FNBZ'; room='ITL'} , @{start='11:00'; end='13:00'; code='DFC40343'; lecturer='FFWA'; room='APL'} , @{start='16:00'; end='17:00'; code='PA'; lecturer='FAZO'; room='MML'} );
        'JUMAAT'=@(@{start='08:00'; end='11:00'; code='DFP40443'; lecturer='FNOM'; room='APL'} );
    } },
    @{ file='semester4_s3.html'; institution='POLITEKNIK KUCHING SARAWAK'; program='DIT4-S3'; session='SESI 1 2026/2027'; advisor='AFZAN BINTI OMAR'; schedule=@{
        'ISNIN'=@(@{start='08:00'; end='10:00'; code='DFK40083'; lecturer='FHBA'; room='NWL'} , @{start='10:00'; end='13:00'; code='DFK40073'; lecturer='FKEF'; room='APL'} );
        'SELASA'=@(@{start='08:00'; end='11:00'; code='DFK30023'; lecturer='FNMP'; room='ITL'} , @{start='11:00'; end='14:00'; code='DFN40503'; lecturer='FAAN'; room='ITL'} , @{start='15:00'; end='17:00'; code='DFC40343'; lecturer='FRBM'; room='APL'} );
        'RABU'=@(@{start='08:00'; end='10:00'; code='DFN40553'; lecturer='FSFM'; room='NWL'} , @{start='10:00'; end='12:00'; code='DFN40553'; lecturer='FSFM'; room='NWL'} , @{start='12:00'; end='13:00'; code='DFK40073'; lecturer='FKEF'; room='-'} , @{start='14:00'; end='17:00'; code='DFK40083'; lecturer='FHBA'; room='DCN'} );
        'KHAMIS'=@(@{start='08:00'; end='11:00'; code='DFK30023'; lecturer='FNMP'; room='PGL2'} , @{start='13:00'; end='15:00'; code='DFC40343'; lecturer='FRBM'; room='CSD'} , @{start='16:00'; end='17:00'; code='PA'; lecturer='FAFO'; room='-'} );
        'JUMAAT'=@(@{start='08:00'; end='10:00'; code='DFN40503'; lecturer='FAAN'; room='CSD'} , @{start='10:00'; end='12:00'; code='DFN40553'; lecturer='FSFM'; room='NWL'} );
    } },
    @{ file='semester5_s1.html'; institution='POLITEKNIK KUCHING SARAWAK'; program='DIT5-S1'; session='SESI 1 2026/2027'; advisor='DR. ZAKIR HUSSAIN BIN IBRAHIN'; schedule=@{
        'ISNIN'=@(@{start='08:00'; end='11:00'; code='DFK50083'; lecturer='FRBS'; room='PGL2'} );
        'SELASA'=@(@{start='08:00'; end='11:00'; code='DFK50093'; lecturer='FNZH'; room='NWL'} , @{start='11:00'; end='14:00'; code='DFP40463'; lecturer='FWAH'; room='PGL2'} );
        'RABU'=@(@{start='08:00'; end='09:00'; code='DFP40433'; lecturer='FZHI'; room='ITL'} , @{start='09:00'; end='11:00'; code='DFP40433'; lecturer='FZHI'; room='ITL'} , @{start='11:00'; end='12:00'; code='MPU21072'; lecturer='ANFT'; room='BK41(P)'} , @{start='12:00'; end='14:00'; code='DFK50083'; lecturer='FRBS'; room='MML'} );
        'KHAMIS'=@(@{start='08:00'; end='10:00'; code='DFK50093'; lecturer='FNZH'; room='DCN'} , @{start='10:00'; end='12:00'; code='MPU22071'; lecturer='AHAJ'; room='BK45*(G)'} , @{start='12:00'; end='14:00'; code='MPU21072'; lecturer='ANFT'; room='BK37(G)'} , @{start='16:00'; end='17:00'; code='PA'; lecturer='FZHI'; room='CSD'} );
        'JUMAAT'=@(@{start='08:00'; end='10:00'; code='DFP40433'; lecturer='FZHI'; room='MML'} , @{start='14:00'; end='17:00'; code='DFP40463'; lecturer='FWAH'; room='ITL'} );
    } },
    @{ file='semester5_s2.html'; institution='POLITEKNIK KUCHING SARAWAK'; program='DIT5-S2'; session='SESI 1 2026/2027'; advisor='DZULKIPLI BIN MARASAN'; schedule=@{
        'ISNIN'=@(@{start='08:00'; end='10:00'; code='MPU21072'; lecturer='ANFT'; room='BK44 (C)'} , @{start='14:00'; end='17:00'; code='DFK50083'; lecturer='FAAN'; room='MML'} );
        'SELASA'=@(@{start='08:00'; end='10:00'; code='MPU22071'; lecturer='AHAJ'; room='BK12 (B)'} , @{start='10:00'; end='13:00'; code='DFP50463'; lecturer='FZHI'; room='HYP'} , @{start='13:00'; end='15:00'; code='DFP40433'; lecturer='FSNA'; room='PGL1'} );
        'RABU'=@(@{start='08:00'; end='10:00'; code='DFK50083'; lecturer='FAAN'; room='PGL1'} , @{start='10:00'; end='11:00'; code='MPU21072'; lecturer='ANFT/ANKS'; room='BK41 (P)'} , @{start='11:00'; end='13:00'; code='DFK50083'; lecturer='FSCA'; room='DCN'} );
        'KHAMIS'=@(@{start='11:00'; end='13:00'; code='DFK50093'; lecturer='FSCA'; room='NWL'} , @{start='13:00'; end='15:00'; code='DFP50463'; lecturer='FZHI'; room='MML'} , @{start='16:00'; end='17:00'; code='PA'; lecturer='FDBM'; room='-'} );
        'JUMAAT'=@(@{start='08:00'; end='09:00'; code='DFP40433'; lecturer='FSNA'; room='ITL'} , @{start='09:00'; end='11:00'; code='DFP40433'; lecturer='FSNA'; room='ITL'} );
    } },
    @{ file='semester5_s3.html'; institution='POLITEKNIK KUCHING SARAWAK'; program='DIT5-S3'; session='SESI 1 2026/2027'; advisor='NURHAZURA BINTI HASSAN'; schedule=@{
        'ISNIN'=@(@{start='08:00'; end='09:00'; code='MPU21072'; lecturer='ANKS/ANMN'; room='BK30*(A)'} , @{start='09:00'; end='10:00'; code='DFN40543'; lecturer='FSNA'; room='NWL'} , @{start='10:00'; end='12:00'; code='DFK50083'; lecturer='FSNA'; room='PGL1'} , @{start='14:00'; end='15:00'; code='DFN50563'; lecturer='FJAG'; room='DCN'} , @{start='15:00'; end='17:00'; code='DFN50563'; lecturer='FJAG'; room='DCN'} );
        'SELASA'=@(@{start='08:00'; end='10:00'; code='DFK50083'; lecturer='FSNA'; room='PGL2'} , @{start='10:00'; end='12:00'; code='MPU22071'; lecturer='AHAJ'; room='BK41(P)'} );
        'RABU'=@(@{start='08:00'; end='09:00'; code='DFN40543'; lecturer='FSCA'; room='DCN'} , @{start='09:00'; end='11:00'; code='DFN40543'; lecturer='FSCA'; room='DCN'} , @{start='11:00'; end='13:00'; code='DFK50083'; lecturer='FNZH'; room='NWL'} );
        'KHAMIS'=@(@{start='11:00'; end='13:00'; code='MPU21072'; lecturer='ANKS'; room='BK41(P)'} , @{start='13:00'; end='15:00'; code='DFN50563'; lecturer='FJAG'; room='DCN'} , @{start='16:00'; end='17:00'; code='PA'; lecturer='FNZH'; room='-'} );
        'JUMAAT'=@(@{start='08:00'; end='10:00'; code='DFK50083'; lecturer='FNZH'; room='NWL'} , @{start='10:00'; end='12:00'; code='DFN40543'; lecturer='FSCA'; room='DCN'} );
    } },
    @{ file='semester5_s4.html'; institution='POLITEKNIK KUCHING SARAWAK'; program='DIT5-S4'; session='SESI 1 2026/2027'; advisor='TANG SI KING'; schedule=@{
        'ISNIN'=@(@{start='10:00'; end='13:00'; code='DFN40543'; lecturer='FSAN'; room='DCN'} , @{start='14:00'; end='16:00'; code='DFK50083'; lecturer='FRBS'; room='PGL1'} );
        'SELASA'=@(@{start='08:00'; end='09:00'; code='DFN40543'; lecturer='FSAN'; room='DCN'} , @{start='09:00'; end='11:00'; code='DFN40543'; lecturer='FSAN'; room='DCN'} , @{start='11:00'; end='13:00'; code='DFK50093'; lecturer='FRBS'; room='NWL'} , @{start='13:00'; end='15:00'; code='MPU22071'; lecturer='AHAJ'; room='BK45*(G)'} );
        'RABU'=@(@{start='13:00'; end='14:00'; code='MPU21072'; lecturer='ANMN'; room='BK30*(A)'} );
        'KHAMIS'=@(@{start='08:00'; end='11:00'; code='DFK50093'; lecturer='FRBS'; room='NWL'} , @{start='12:00'; end='14:00'; code='MPU21072'; lecturer='ANMN'; room='BPJPA1*(A)'} , @{start='16:00'; end='17:00'; code='PA'; lecturer='FTSK'; room='-'} );
        'JUMAAT'=@(@{start='08:00'; end='10:00'; code='DFN50563'; lecturer='FHBA'; room='DCN'} , @{start='10:00'; end='13:00'; code='DFK50083'; lecturer='FRBS'; room='HYP'} , @{start='14:00'; end='15:00'; code='DFN50563'; lecturer='FHBA'; room='NWL'} , @{start='15:00'; end='17:00'; code='DFN50563'; lecturer='FHBA'; room='NWL'} );
    } }
)

foreach ($item in $programs) {
    $html = New-TimetableHtml $item.program $item.institution $item.session $item.advisor $item.schedule
    $path = Join-Path $PSScriptRoot $item.file
    [System.IO.File]::WriteAllText($path, $html, [System.Text.UTF8Encoding]::new($true))
    Write-Host "Created $($item.file)"
}
