<?php
// crossmatch/helpers/fuzzy.php

function kds_norm(string $s): string {
  $s = trim((string)$s);
  $s = mb_strtolower($s, 'UTF-8');
  $s = preg_replace('/[^a-z0-9\s]/u', ' ', $s);
  $s = preg_replace('/\s+/', ' ', $s);
  return trim($s);
}

function kds_token_sort(string $s): string {
  $toks = preg_split('/\s+/', kds_norm($s), -1, PREG_SPLIT_NO_EMPTY);
  sort($toks, SORT_STRING);
  return implode(' ', $toks);
}

function kds_sim(string $a, string $b): float {
  $a = (string)$a; $b = (string)$b;
  if ($a === '' && $b === '') return 100.0;
  similar_text(kds_norm($a), kds_norm($b), $pct1);
  $aa = kds_token_sort($a); $bb = kds_token_sort($b);
  $maxLen = max(strlen($aa), strlen($bb));
  if ($maxLen === 0) $pct2 = 100.0;
  else {
    $lev = levenshtein($aa, $bb);
    $pct2 = max(0.0, (1 - ($lev / $maxLen)) * 100.0);
  }
  return max($pct1, $pct2);
}

function kds_norm_birth(string $d): string {
  $d = trim((string)$d);
  if ($d === '') return '';
  $x = str_replace(['.', ' '], ['/', '/'], $d);
  $ts = strtotime($x);
  if ($ts !== false) return date('Y-m-d', $ts);
  return kds_norm($d);
}

function kds_score_pair(array $a, array $b, array $opts): array {
  $weights = $opts['weights'] ?? ['name'=>0.60, 'birth'=>0.20, 'address'=>0.20];
  $birthRule = $opts['birthdate_rule'] ?? 'strict';

  $parts = [];
  $parts[] = kds_sim($a['lastName']  ?? '', $b['lastName']  ?? '');
  $parts[] = kds_sim($a['firstName'] ?? '', $b['firstName'] ?? '');
  $parts[] = kds_sim($a['middleName']?? '', $b['middleName']?? '');
  $parts[] = kds_sim($a['ext']       ?? '', $b['ext']       ?? '');
  $nameScore = array_sum($parts) / max(1, count($parts));

  $na = kds_norm_birth($a['birthDate'] ?? '');
  $nb = kds_norm_birth($b['birthDate'] ?? '');
  if ($birthRule === 'strict') {
    $birthScore = ($na !== '' && $nb !== '' && $na === $nb) ? 100.0 : 0.0;
  } else {
    $birthScore = ($na !== '' && $nb !== '') ? ($na === $nb ? 100.0 : kds_sim($na, $nb)) : kds_sim((string)($a['birthDate'] ?? ''), (string)($b['birthDate'] ?? ''));
  }

  $addrParts = [];
  $addrParts[] = kds_sim($a['barangay'] ?? '', $b['barangay'] ?? '');
  $addrParts[] = kds_sim($a['lgu']      ?? '', $b['lgu']      ?? '');
  $addrParts[] = kds_sim($a['province'] ?? '', $b['province'] ?? '');
  $addrScore = array_sum($addrParts) / max(1, count($addrParts));

  $overall = ($nameScore * ($weights['name'] ?? 0.60))
           + ($birthScore * ($weights['birth'] ?? 0.20))
           + ($addrScore * ($weights['address'] ?? 0.20));

  return [
    'overall'    => round($overall, 4),
    'nameScore'  => round($nameScore, 4),
    'birthScore' => round($birthScore, 4),
    'addrScore'  => round($addrScore, 4),
  ];
}

function topCandidatesForRecord(array $record, array $candidates, array $opts = []): array {
  $topN = (int)($opts['topN'] ?? 3);
  $threshold = (float)($opts['threshold'] ?? 0.0);
  $ranked = [];
  foreach ($candidates as $cand) {
    $s = kds_score_pair($record, $cand, $opts);
    $ranked[] = [
      'candidate'  => $cand,
      'score'      => round($s['overall'], 2),
      'nameScore'  => round($s['nameScore'], 2),
      'birthScore' => round($s['birthScore'], 2),
      'addrScore'  => round($s['addrScore'], 2),
    ];
  }
  usort($ranked, function($x,$y){ return $y['score'] <=> $x['score']; });
  if ($threshold > 0) $ranked = array_values(array_filter($ranked, fn($r)=> $r['score'] >= $threshold));
  return array_slice($ranked, 0, max(1, $topN));
}

function loadAllCandidatesFromDb_mysqli(mysqli $conn): array {
  $out = [];
  $sql = "SELECT lastName, firstName, middleName, ext, birthDate, barangay, lgu, province FROM meb";
  $res = $conn->query($sql);
  if ($res) {
    while ($r = $res->fetch_assoc()) $out[] = $r;
  }
  return $out;
}
