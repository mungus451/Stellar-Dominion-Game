<?php
// public/schema_diff.php
// Upload two MariaDB/MySQL dumps and compare TABLE+COLUMN definitions only.
// No DB connection needed. Outputs a human-readable diff.
// NOTES:
// - Compares table presence and column sets+definitions (type/null/default/extra).
// - Ignores data rows, keys/indexes, foreign keys, and AUTO_INCREMENT counters.
// - Case-insensitive on identifiers and types, trims whitespace.

// ---------- helpers ----------
function norm_ws(string $s): string {
    return trim(preg_replace('/\s+/u', ' ', $s));
}
function norm_ident(string $s): string {
    return strtolower(trim($s, " \t\n\r\0\x0B`"));
}
function parse_schema(string $sql): array {
    // Return: ['tables' => [table => ['columns' => [col => def_string]]]]
    $result = ['tables' => []];

    // Drop comments and DELIMITER noise quickly
    $sql = preg_replace('~/\*[^*]*\*+(?:[^/*][^*]*\*+)*/~', '', $sql); // /* ... */
    $sql = preg_replace('~^\s*--.*$~m', '', $sql);                     // -- ...
    $sql = preg_replace('~^\s*#.*$~m',  '', $sql);                     // # ...

    // Extract CREATE TABLE blocks
    $re = '~CREATE\s+TABLE\s+`?([A-Za-z0-9_]+)`?\s*\((.*?)\)\s*ENGINE=~is';
    if (preg_match_all($re, $sql, $m, PREG_SET_ORDER)) {
        foreach ($m as $block) {
            $table = norm_ident($block[1]);
            $body  = $block[2];

            // Split lines at commas, but avoid commas inside quotes/parens (rough but effective)
            $parts = preg_split('/,(?=(?:[^()\'"]|\'[^\']*\'|"[^"]*")*$)/', $body);

            $cols = [];
            foreach ($parts as $line) {
                $line = trim($line);
                if ($line === '') continue;

                // Column lines start with backtick or valid ident
                if (preg_match('~^`?([A-Za-z0-9_]+)`?\s+(.*)$~', $line, $cm)) {
                    $col = norm_ident($cm[1]);
                    // Skip constraints/keys
                    $prefix = strtolower($col);
                    if (in_array($prefix, ['primary','unique','key','index','constraint','foreign'], true)) {
                        continue;
                    }
                    $def = $cm[2];

                    // Normalize the column definition: lower types/keywords, but keep default literals
                    // Remove trailing COMMENTs and commas if any persisted
                    $def = preg_replace('~\s+COMMENT\s+\'[^\']*\'~i', '', $def);
                    $def = rtrim($def, ',');
                    $def_lc = strtolower($def);

                    // Normalize AUTO_INCREMENT and binary attributes spacing
                    $def_lc = norm_ws($def_lc);

                    $cols[$col] = $def_lc;
                }
            }

            ksort($cols);
            $result['tables'][$table] = ['columns' => $cols];
        }
    }
    ksort($result['tables']);
    return $result;
}
function array_key_diff(array $a, array $b): array {
    return array_values(array_diff(array_keys($a), array_keys($b)));
}
function compare_schemas(array $A, array $B): array {
    $out = [
        'only_in_a' => [],
        'only_in_b' => [],
        'tables_compared' => [],
    ];

    $tablesA = $A['tables'] ?? [];
    $tablesB = $B['tables'] ?? [];

    // Table presence
    $out['only_in_a'] = array_key_diff($tablesA, $tablesB);
    $out['only_in_b'] = array_key_diff($tablesB, $tablesA);

    // Common tables -> compare columns/defs
    $common = array_intersect(array_keys($tablesA), array_keys($tablesB));
    sort($common);
    foreach ($common as $t) {
        $colsA = $tablesA[$t]['columns'];
        $colsB = $tablesB[$t]['columns'];

        $onlyA = array_key_diff($colsA, $colsB);
        $onlyB = array_key_diff($colsB, $colsA);

        $diffDefs = [];
        foreach (array_intersect(array_keys($colsA), array_keys($colsB)) as $c) {
            $da = $colsA[$c];
            $db = $colsB[$c];

            // ignore AUTO_INCREMENT differences inside column extras if present
            $da_clean = preg_replace('~\bauto_increment\b~', '', $da);
            $db_clean = preg_replace('~\bauto_increment\b~', '', $db);
            $da_clean = norm_ws($da_clean);
            $db_clean = norm_ws($db_clean);

            if ($da_clean !== $db_clean) {
                $diffDefs[$c] = ['a' => $colsA[$c], 'b' => $colsB[$c]];
            }
        }

        if ($onlyA || $onlyB || $diffDefs) {
            $out['tables_compared'][$t] = [
                'only_in_a' => $onlyA,
                'only_in_b' => $onlyB,
                'def_mismatch' => $diffDefs,
            ];
        }
    }

    return $out;
}
function render_html(?string $msg, ?array $report): void {
    echo '<!doctype html><meta charset="utf-8"><title>Schema Diff</title>';
    echo '<style>body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:24px;color:#e5e7eb;background:#0b1220}'
        .'.panel{background:#0f172a;padding:16px;border-radius:12px;margin-bottom:16px;border:1px solid #334155}'
        .'h1{color:#93c5fd} h2{color:#7dd3fc} code,pre{background:#111827;color:#e5e7eb;padding:8px;border-radius:8px;display:block;white-space:pre-wrap}'
        .'.ok{color:#22c55e}.warn{color:#f59e0b}.bad{color:#f87171}.muted{color:#94a3b8}'
        .'table{width:100%;border-collapse:collapse} td,th{border:1px solid #334155;padding:6px;vertical-align:top}'
        .'.tbl{overflow:auto;max-height:60vh}'
        .'</style>';

    echo '<div class="panel"><h1>Schema Diff (Tables & Columns)</h1>';
    echo '<form method="post" enctype="multipart/form-data">'
        .'<div>Dump A: <input type="file" name="dump_a" required> &nbsp; Dump B: <input type="file" name="dump_b" required> '
        .'<button type="submit">Compare</button></div>'
        .'<div class="muted" style="margin-top:8px">Compares CREATE TABLE definitions; ignores data, indexes, FKs, and AUTO_INCREMENT counters.</div>'
        .'</form></div>';

    if ($msg !== null) {
        echo '<div class="panel"><pre>'.htmlspecialchars($msg).'</pre></div>';
    }

    if ($report !== null) {
        $onlyA = $report['only_in_a'];
        $onlyB = $report['only_in_b'];
        $issues = $report['tables_compared'];

        if (!$onlyA && !$onlyB && !$issues) {
            echo '<div class="panel ok"><strong>Result:</strong> Schemas are IDENTICAL for tables & columns.</div>';
            return;
        }

        echo '<div class="panel bad"><strong>Result:</strong> Schemas differ.</div>';

        if ($onlyA) {
            echo '<div class="panel warn"><h2>Tables only in Dump A</h2><pre>'.htmlspecialchars(implode("\n", $onlyA)).'</pre></div>';
        }
        if ($onlyB) {
            echo '<div class="panel warn"><h2>Tables only in Dump B</h2><pre>'.htmlspecialchars(implode("\n", $onlyB)).'</pre></div>';
        }

        if ($issues) {
            echo '<div class="panel"><h2>Common tables with differences</h2><div class="tbl"><table><thead><tr><th>Table</th><th>Only in A</th><th>Only in B</th><th>Column definition mismatches</th></tr></thead><tbody>';
            foreach ($issues as $t => $d) {
                echo '<tr><td><code>'.$t.'</code></td>';
                echo '<td><pre>'.htmlspecialchars(implode("\n", $d['only_in_a'] ?? []) ?: '—').'</pre></td>';
                echo '<td><pre>'.htmlspecialchars(implode("\n", $d['only_in_b'] ?? []) ?: '—').'</pre></td>';
                if (!empty($d['def_mismatch'])) {
                    $blk = '';
                    foreach ($d['def_mismatch'] as $c => $pair) {
                        $blk .= "Column `$c`:\n  A: ".$pair['a']."\n  B: ".$pair['b']."\n\n";
                    }
                    echo '<td><pre>'.htmlspecialchars(trim($blk)).'</pre></td>';
                } else {
                    echo '<td>—</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table></div></div>';
        }
    }
}

// ---------- main ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    render_html(null, null);
    exit;
}

if (!isset($_FILES['dump_a']['tmp_name'], $_FILES['dump_b']['tmp_name'])) {
    render_html("Please upload two .sql files.", null);
    exit;
}
$a = file_get_contents($_FILES['dump_a']['tmp_name']);
$b = file_get_contents($_FILES['dump_b']['tmp_name']);
if ($a === false || $b === false) {
    render_html("Failed to read uploaded file(s).", null);
    exit;
}

$schemaA = parse_schema($a);
$schemaB = parse_schema($b);
$report  = compare_schemas($schemaA, $schemaB);

// Optional quick summary string
$summary = "Tables in A: ".count($schemaA['tables'])."\n"
         . "Tables in B: ".count($schemaB['tables'])."\n";

render_html($summary, $report);
