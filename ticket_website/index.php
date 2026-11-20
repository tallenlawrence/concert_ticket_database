<?php
require_once 'db.php';
session_start();

/* ============================================================
   HELPERS
   ============================================================ */
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function cell($v) { return $v === null ? '<i class="null">NULL</i>' : h($v); }
function flash($type, $msg) { $_SESSION['flash'][] = [$type, $msg]; }
function go($url) { header("Location: $url"); exit; }
function ident($s) {
    $s = trim((string)$s);
    return preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $s) ? $s : null;
}
function getCols($conn, $t) {
    $cols = []; $pks = [];
    $m = $conn->query("SHOW COLUMNS FROM `$t`");
    while ($c = $m->fetch_assoc()) {
        $cols[] = $c;
        if ($c['Key'] === 'PRI') $pks[] = $c['Field'];
    }
    return [$cols, $pks];
}
function runSql($conn, $sql) {
    $out = ['h' => [], 'r' => [], 'err' => '', 'note' => ''];
    try {
        $res = $conn->query($sql);
        if ($res instanceof mysqli_result) {
            $out['h'] = array_column($res->fetch_fields(), 'name');
            $out['r'] = $res->fetch_all(MYSQLI_ASSOC);
            $res->free();
        } else {
            $out['note'] = "OK. Rows affected: " . $conn->affected_rows;
        }
        // flush extra result sets (needed after CALL)
        while ($conn->more_results()) {
            $conn->next_result();
            $x = $conn->store_result();
            if ($x) $x->free();
        }
    } catch (mysqli_sql_exception $e) {
        $out['err'] = $e->getMessage();
    }
    return $out;
}
function listViews($conn) {
    $r = $conn->query("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");
    return array_column($r->fetch_all(MYSQLI_ASSOC), 'TABLE_NAME');
}
function listTriggers($conn) {
    $r = $conn->query("SELECT TRIGGER_NAME, ACTION_TIMING, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, ACTION_STATEMENT
                       FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() ORDER BY TRIGGER_NAME");
    return $r->fetch_all(MYSQLI_ASSOC);
}
function getSavedQuery($conn, $id) {
    $st = $conn->prepare("SELECT * FROM Saved_Query WHERE query_id = ?");
    $st->bind_param("i", $id);
    $st->execute();
    return $st->get_result()->fetch_assoc();
}

/* ============================================================
   BOOTSTRAP: Saved_Query table (stores the saved queries)
   ============================================================ */
$conn->query("CREATE TABLE IF NOT EXISTS Saved_Query (
    query_id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(150) NOT NULL,
    description VARCHAR(500),
    sql_text TEXT NOT NULL
)");

$seed = [
    ["Q1: Upcoming Shows at Gabe's",
     "Lists every future concert at Gabe's with date, start time and all performers in one comma-separated list. LEFT JOINs + GROUP_CONCAT.",
<<<'SQL'
SELECT 
    c.concert_id, 
    c.concert_date, 
    c.start_time, 
    GROUP_CONCAT(p.performer_name SEPARATOR ', ') AS performers
FROM Concert c
LEFT JOIN Concert_Performer o ON c.concert_id = o.concert_id
LEFT JOIN Performer p ON o.performer_id = p.performer_id
WHERE c.venue_id = 20005
AND c.concert_date > CURRENT_DATE
GROUP BY c.concert_id, c.concert_date, c.start_time;
SQL
    ],
    ["Q2: Tyler, the Creator Shows",
     "All upcoming concerts for Tyler, the Creator with venue name, state and city. Joins Concert, Concert_Performer and Venue.",
<<<'SQL'
SELECT c.concert_id, c.concert_date,
    v.venue_name, v.state, v.city
FROM Concert c
JOIN Concert_Performer p ON c.concert_id = p.concert_id
JOIN Venue v ON v.venue_id = c.venue_id
WHERE p.performer_id = 80014
AND c.concert_date > CURRENT_DATE;
SQL
    ],
    ["Q3: Booking #44002 Total",
     "Ticket count and total cost for booking 44002. Aggregate (COUNT, SUM) on Ticket.",
<<<'SQL'
SELECT COUNT(*) AS ticket_count, SUM(t.price) AS total_cost
FROM Ticket t
WHERE t.booking_id = 44002;
SQL
    ],
    ["Q4: Concert Sales & Revenue",
     "Tickets sold and total revenue per concert. Join + aggregate grouped by concert.",
<<<'SQL'
SELECT
    c.concert_id,
    c.concert_date,
    COUNT(t.ticket_id) AS tickets_sold,
    SUM(t.price) AS total_revenue
FROM Concert c
JOIN Ticket t ON c.concert_id = t.concert_id
GROUP BY c.concert_id, c.concert_date;
SQL
    ],
    ["Q5: Above-Average Buyers",
     "Customers who bought more tickets than average. Uses the loyalty view and a subquery.",
<<<'SQL'
SELECT
    customer_id,
    name,
    total_tick_bought,
    current_tier
FROM loyalty
WHERE total_tick_bought > (
    SELECT AVG(total_tick_bought)
    FROM loyalty
);
SQL
    ],
    ["Procedure: Get_Customer_Bookings",
     "Calls the stored procedure for one customer's bookings and tickets. Edit the ID in the SQL to change customer.",
     "CALL Get_Customer_Bookings(50042);"
    ],
    ["Function: Get_Concert_Revenue",
     "Calls the stored function for one concert's total ticket revenue. Edit the ID in the SQL to change concert.",
     "SELECT Get_Concert_Revenue(30043) AS total_revenue;"
    ],
];
$cnt = (int)$conn->query("SELECT COUNT(*) AS c FROM Saved_Query")->fetch_assoc()['c'];
if ($cnt === 0) {
    $st = $conn->prepare("INSERT INTO Saved_Query (label, description, sql_text) VALUES (?, ?, ?)");
    foreach ($seed as $s) {
        $st->bind_param("sss", $s[0], $s[1], $s[2]);
        $st->execute();
    }
}

/* ============================================================
   TABLE / VIEW LISTS
   ============================================================ */
$preferred = ['Reward_Tier', 'Venue', 'Concert', 'Customer', 'Booking', 'Ticket', 'Performer', 'Concert_Performer', 'Employee'];
$found = array_column(
    $conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetch_all(MYSQLI_ASSOC),
    'TABLE_NAME'
);
$foundLower = array_map('strtolower', $found);
$tables = [];
foreach ($preferred as $p) {
    if (in_array(strtolower($p), $foundLower)) $tables[] = $p;
}
foreach ($found as $f) {
    if (!in_array(strtolower($f), array_map('strtolower', $tables)) && strtolower($f) !== 'saved_query') $tables[] = $f;
}

$page = $_GET['page'] ?? (isset($_GET['table']) ? 'tables' : 'home');
if (!in_array($page, ['home', 'tables', 'queries', 'views', 'triggers'])) $page = 'home';

/* ============================================================
   POST ACTIONS (redirect after post, messages via flash)
   ============================================================ */
$adhoc = null; $adhocSql = ''; $adhocDesc = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';

    if ($do === 'query_run') {
        // run without saving, rendered directly
        $adhocSql = trim($_POST['sql_text'] ?? '');
        $adhoc = runSql($conn, $adhocSql);
        $page = 'queries';
    } else {
        $back = 'index.php';
        try {
            switch ($do) {

                /* ---- saved queries ---- */
                case 'query_save':
                    $back = 'index.php?page=queries';
                    $id = (int)($_POST['query_id'] ?? 0);
                    $label = trim($_POST['label'] ?? '');
                    $desc = trim($_POST['description'] ?? '');
                    $sqlt = trim($_POST['sql_text'] ?? '');
                    if ($label === '' || $sqlt === '') { flash('err', 'Name and SQL are required.'); break; }
                    if ($id) {
                        $st = $conn->prepare("UPDATE Saved_Query SET label = ?, description = ?, sql_text = ? WHERE query_id = ?");
                        $st->bind_param("sssi", $label, $desc, $sqlt, $id);
                    } else {
                        $st = $conn->prepare("INSERT INTO Saved_Query (label, description, sql_text) VALUES (?, ?, ?)");
                        $st->bind_param("sss", $label, $desc, $sqlt);
                    }
                    $st->execute();
                    flash('ok', 'Query saved.');
                    break;

                case 'query_delete':
                    $back = 'index.php?page=queries';
                    $id = (int)($_POST['query_id'] ?? 0);
                    $st = $conn->prepare("DELETE FROM Saved_Query WHERE query_id = ?");
                    $st->bind_param("i", $id);
                    $st->execute();
                    flash('ok', 'Query deleted.');
                    break;

                /* ---- views ---- */
                case 'view_save':
                    $back = 'index.php?page=views';
                    $name = ident($_POST['name'] ?? '');
                    $sqlv = rtrim(trim($_POST['sql'] ?? ''), "; \t\r\n");
                    $editing = !empty($_POST['orig']);
                    if (!$name) { flash('err', 'View name must be letters, numbers and underscores.'); break; }
                    if (!preg_match('/^\s*(select|with|\()/i', $sqlv)) { flash('err', 'A view must be defined by a SELECT statement.'); break; }
                    $conn->query(($editing ? "CREATE OR REPLACE VIEW" : "CREATE VIEW") . " `$name` AS $sqlv");
                    flash('ok', "View `$name` " . ($editing ? 'updated.' : 'created.'));
                    break;

                case 'view_drop':
                    $back = 'index.php?page=views';
                    $name = ident($_POST['name'] ?? '');
                    if ($name && in_array(strtolower($name), array_map('strtolower', listViews($conn)))) {
                        $conn->query("DROP VIEW `$name`");
                        flash('ok', "View `$name` dropped.");
                    } else flash('err', 'Unknown view.');
                    break;

                /* ---- triggers ---- */
                case 'trigger_save':
                    $back = 'index.php?page=triggers';
                    $ddl = trim($_POST['ddl'] ?? '');
                    $ddl = trim(preg_replace('/^\s*DELIMITER\s+\S+\s*$/mi', '', $ddl));
                    $ddl = trim(preg_replace('/(\$\$|\/\/)\s*$/', '', $ddl));
                    if (!preg_match('/^CREATE\s+(DEFINER\s*=\s*\S+\s+)?TRIGGER\s+/i', $ddl)) {
                        flash('err', 'Statement must start with CREATE TRIGGER.');
                        break;
                    }
                    $orig = ident($_POST['orig'] ?? '');
                    $old = null;
                    if ($orig) {
                        $row = $conn->query("SHOW CREATE TRIGGER `$orig`")->fetch_assoc();
                        $old = $row['SQL Original Statement'] ?? null;
                        $conn->query("DROP TRIGGER IF EXISTS `$orig`");
                    }
                    try {
                        $conn->query($ddl);
                        flash('ok', 'Trigger saved.');
                    } catch (mysqli_sql_exception $e) {
                        if ($old) {
                            try { $conn->query($old); } catch (mysqli_sql_exception $e2) {}
                            flash('err', 'Trigger not saved (original restored): ' . $e->getMessage());
                        } else {
                            flash('err', 'Trigger not created: ' . $e->getMessage());
                        }
                    }
                    break;

                case 'trigger_drop':
                    $back = 'index.php?page=triggers';
                    $name = ident($_POST['name'] ?? '');
                    if ($name) { $conn->query("DROP TRIGGER IF EXISTS `$name`"); flash('ok', "Trigger `$name` dropped."); }
                    break;

                /* ---- tables ---- */
                case 'table_create':
                    $back = 'index.php?page=tables';
                    $ddl = trim($_POST['ddl'] ?? '');
                    if (!preg_match('/^\s*create\s+table\b/i', $ddl)) { flash('err', 'Statement must start with CREATE TABLE.'); break; }
                    $conn->query($ddl);
                    flash('ok', 'Table created.');
                    break;

                case 'table_drop':
                    $back = 'index.php?page=tables';
                    $t = $_POST['table'] ?? '';
                    if (in_array($t, $tables, true)) {
                        $conn->query("DROP TABLE `$t`");
                        flash('ok', "Table `$t` dropped.");
                    } else flash('err', 'Unknown table.');
                    break;

                /* ---- records ---- */
                case 'rec_create':
                case 'rec_update':
                case 'rec_delete':
                    $t = $_POST['table'] ?? '';
                    $ret = (($_POST['ret'] ?? '') === 'home') ? 'home' : 'tables';
                    if (!in_array($t, $tables, true)) { flash('err', 'Unknown table.'); break; }
                    $back = "index.php?page=$ret&table=" . urlencode($t);
                    [$cols, $pks] = getCols($conn, $t);

                    if ($do === 'rec_create') {
                        $names = []; $ph = []; $vals = []; $types = '';
                        foreach ($cols as $c) {
                            $v = trim($_POST[$c['Field']] ?? '');
                            $names[] = "`{$c['Field']}`"; $ph[] = '?';
                            $vals[] = ($v === '') ? null : $v; $types .= 's';
                        }
                        $st = $conn->prepare("INSERT INTO `$t` (" . implode(', ', $names) . ") VALUES (" . implode(', ', $ph) . ")");
                        $st->bind_param($types, ...$vals);
                        $st->execute();
                        flash('ok', "Record added to $t.");
                    } elseif ($do === 'rec_update') {
                        $set = []; $vals = []; $types = '';
                        foreach ($cols as $c) {
                            $v = trim($_POST[$c['Field']] ?? '');
                            $set[] = "`{$c['Field']}` = ?";
                            $vals[] = ($v === '') ? null : $v; $types .= 's';
                        }
                        $where = [];
                        foreach ($pks as $pk) { $where[] = "`$pk` = ?"; $vals[] = $_POST['orig_' . $pk] ?? ''; $types .= 's'; }
                        $st = $conn->prepare("UPDATE `$t` SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $where));
                        $st->bind_param($types, ...$vals);
                        $st->execute();
                        flash('ok', "Record updated in $t.");
                    } else {
                        $where = []; $vals = []; $types = '';
                        foreach ($pks as $pk) { $where[] = "`$pk` = ?"; $vals[] = $_POST['pk_' . $pk] ?? ''; $types .= 's'; }
                        $st = $conn->prepare("DELETE FROM `$t` WHERE " . implode(' AND ', $where));
                        $st->bind_param($types, ...$vals);
                        $st->execute();
                        flash('ok', "Record deleted from $t.");
                    }
                    break;

                default:
                    flash('err', 'Unknown action.');
            }
        } catch (mysqli_sql_exception $e) {
            flash('err', $e->getMessage());
        }
        go($back);
    }
}

/* ============================================================
   RENDER HELPERS
   ============================================================ */
function resultWin($title, $desc, $sql, $o) {
    echo '<div class="win" id="results"><h2>' . h($title) . '</h2><div class="body">';
    if ($desc) echo '<p class="desc">' . h($desc) . '</p>';
    if ($o['err']) echo '<div class="msg err">' . h($o['err']) . '</div>';
    elseif ($o['note']) echo '<div class="msg ok">' . h($o['note']) . '</div>';
    elseif ($o['h']) {
        echo '<div class="sunken"><table class="grid"><tr>';
        foreach ($o['h'] as $hd) echo '<th>' . h($hd) . '</th>';
        echo '</tr>';
        foreach ($o['r'] as $r) {
            echo '<tr>';
            foreach ($o['h'] as $hd) echo '<td>' . cell($r[$hd]) . '</td>';
            echo '</tr>';
        }
        if (!$o['r']) echo '<tr><td colspan="' . count($o['h']) . '">No rows.</td></tr>';
        echo '</table></div>';
    }
    echo '<details><summary>show SQL</summary><pre>' . h($sql) . '</pre></details></div></div>';
}

function queryList($conn, $ctx, $crud, $tbl) {
    $r = $conn->query("SELECT * FROM Saved_Query ORDER BY query_id");
    echo '<div class="sunken"><table class="grid"><tr><th>#</th><th>Name</th><th>Description</th><th>Actions</th></tr>';
    while ($q = $r->fetch_assoc()) {
        $id = (int)$q['query_id'];
        $extra = $tbl ? '&table=' . urlencode($tbl) : '';
        echo '<tr><td>' . $id . '</td><td>' . h($q['label']) . '</td><td class="wrap">' . h($q['description']) . '</td><td class="act">';
        echo '<a class="btn" href="index.php?page=' . $ctx . '&run=' . $id . $extra . '#results">Run</a> ';
        if ($crud) {
            echo '<a class="btn" href="index.php?page=queries&edit=' . $id . '#form">Edit</a> ';
            echo '<form method="post" action="index.php" onsubmit="return confirm(\'Delete this query?\');">'
               . '<input type="hidden" name="do" value="query_delete"><input type="hidden" name="query_id" value="' . $id . '">'
               . '<button class="btn danger">Delete</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</table></div>';
}

function recordsPanel($conn, $t, $ret) {
    [$cols, $pks] = getCols($conn, $t);
    $base = "index.php?page=$ret&table=" . urlencode($t);
    $edit = null;
    if ($pks && isset($_GET['edit'])) {
        $w = []; $v = []; $ty = '';
        foreach ($pks as $pk) { $w[] = "`$pk` = ?"; $v[] = $_GET['pk_' . $pk] ?? ''; $ty .= 's'; }
        $st = $conn->prepare("SELECT * FROM `$t` WHERE " . implode(' AND ', $w));
        $st->bind_param($ty, ...$v);
        $st->execute();
        $edit = $st->get_result()->fetch_assoc();
    }
    ?>
    <div class="win" id="rec">
        <h2><?= $edit ? 'Edit record' : 'Add new record' ?> :: <?= h($t) ?></h2>
        <div class="body">
            <form method="post" action="index.php">
                <input type="hidden" name="do" value="<?= $edit ? 'rec_update' : 'rec_create' ?>">
                <input type="hidden" name="table" value="<?= h($t) ?>">
                <input type="hidden" name="ret" value="<?= h($ret) ?>">
                <?php if ($edit): foreach ($pks as $pk): ?>
                    <input type="hidden" name="orig_<?= h($pk) ?>" value="<?= h($edit[$pk]) ?>">
                <?php endforeach; endif; ?>
                <div class="fields">
                    <?php foreach ($cols as $c): $f = $c['Field']; ?>
                        <label><span><?= h($f) ?><?= $c['Key'] === 'PRI' ? ' *' : '' ?></span>
                            <input type="text" name="<?= h($f) ?>" value="<?= $edit ? h($edit[$f]) : '' ?>" placeholder="<?= h($c['Type']) ?>">
                        </label>
                    <?php endforeach; ?>
                </div>
                <button class="btn" type="submit"><?= $edit ? 'Save changes' : 'Add record' ?></button>
                <?php if ($edit): ?><a class="btn" href="<?= h($base) ?>">Cancel</a><?php endif; ?>
                <span class="hint">* primary key. Blank = NULL.</span>
            </form>
        </div>
    </div>

    <?php $rows = $conn->query("SELECT * FROM `$t`"); ?>
    <div class="win">
        <h2><?= h($t) ?> :: <?= $rows->num_rows ?> row(s)</h2>
        <div class="body">
            <div class="sunken">
                <table class="grid">
                    <tr>
                        <?php foreach ($cols as $c): ?><th><?= h($c['Field']) ?></th><?php endforeach; ?>
                        <?php if ($pks): ?><th>Actions</th><?php endif; ?>
                    </tr>
                    <?php while ($row = $rows->fetch_assoc()): ?>
                        <tr>
                            <?php foreach ($cols as $c): ?><td><?= cell($row[$c['Field']]) ?></td><?php endforeach; ?>
                            <?php if ($pks): ?>
                                <td class="act">
                                    <?php
                                    $qs = $base . "&edit=1";
                                    foreach ($pks as $pk) $qs .= "&pk_" . urlencode($pk) . "=" . urlencode($row[$pk]);
                                    ?>
                                    <a class="btn" href="<?= h($qs) ?>#rec">Edit</a>
                                    <form method="post" action="index.php" onsubmit="return confirm('Delete this record?');">
                                        <input type="hidden" name="do" value="rec_delete">
                                        <input type="hidden" name="table" value="<?= h($t) ?>">
                                        <input type="hidden" name="ret" value="<?= h($ret) ?>">
                                        <?php foreach ($pks as $pk): ?>
                                            <input type="hidden" name="pk_<?= h($pk) ?>" value="<?= h($row[$pk]) ?>">
                                        <?php endforeach; ?>
                                        <button class="btn danger">Delete</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>
    </div>
    <?php
}

$flashes = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
$runId = (int)($_GET['run'] ?? 0);
$nav = ['home' => 'Home', 'tables' => 'Tables', 'queries' => 'Queries', 'views' => 'Views', 'triggers' => 'Triggers'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ConcertTicketDB Manager</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; background: #008080; color: #000; font: 12px/1.35 "Courier New", Courier, monospace; }
    .app { display: grid; grid-template-columns: 170px 1fr; gap: 8px; padding: 8px; align-items: start; }
    .win { background: #c0c0c0; border: 2px solid; border-color: #fff #404040 #404040 #fff; box-shadow: 1px 1px 0 #000; margin-bottom: 8px; }
    .win > h2 { margin: 0; padding: 2px 6px; background: linear-gradient(90deg, #000080, #1084d0); color: #fff; font-size: 12px; letter-spacing: .5px; }
    .win .body { padding: 6px; }
    .side { position: sticky; top: 8px; }
    .side a { display: block; padding: 3px 8px; color: #000; text-decoration: none; border: 1px solid transparent; }
    .side a:hover { border-color: #fff #404040 #404040 #fff; }
    .side a.on { background: #000080; color: #fff; }
    .sunken { background: #fff; border: 2px solid; border-color: #404040 #fff #fff #404040; overflow: auto; max-height: 420px; }
    table.grid { border-collapse: collapse; width: 100%; }
    .grid th, .grid td { border: 1px solid #a0a0a0; padding: 1px 6px; text-align: left; white-space: nowrap; vertical-align: top; }
    .grid td.wrap { white-space: normal; min-width: 240px; }
    .grid th { background: #c0c0c0; position: sticky; top: 0; }
    .grid tr:nth-child(even) td { background: #f0f0f0; }
    .act form { display: inline; }
    .btn, button { font: inherit; background: #c0c0c0; color: #000; border: 2px solid; border-color: #fff #404040 #404040 #fff; padding: 0 8px; cursor: pointer; text-decoration: none; display: inline-block; line-height: 1.4; }
    .btn:active, button:active { border-color: #404040 #fff #fff #404040; }
    .btn.danger { color: #a00000; }
    input[type=text], input[type=number], select, textarea { font: inherit; background: #fff; border: 2px solid; border-color: #404040 #fff #fff #404040; padding: 1px 4px; width: 100%; }
    select { width: auto; min-width: 180px; }
    textarea { resize: vertical; }
    .fields { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 4px 10px; margin-bottom: 6px; }
    .fields span { display: block; font-weight: bold; }
    .msg { padding: 3px 8px; margin-bottom: 6px; border: 1px solid #000; }
    .msg.ok { background: #c8f7c8; }
    .msg.err { background: #ffc8c8; }
    .desc, .hint { color: #404040; margin: 0 0 6px; }
    .hint { margin-left: 8px; }
    .null { color: #888; }
    pre { background: #fff; border: 2px solid; border-color: #404040 #fff #fff #404040; padding: 4px; margin: 4px 0; overflow: auto; white-space: pre-wrap; }
    details summary { cursor: pointer; margin-top: 6px; }
    .row { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .field { margin-bottom: 6px; } .field > span { display: block; font-weight: bold; }
    @media (max-width: 700px) { .app { grid-template-columns: 1fr; } .side { position: static; } }
</style>
</head>
<body>
<div class="app">

    <!-- SIDEBAR -->
    <div class="win side">
        <h2>ConcertDB.exe</h2>
        <div class="body" style="padding:4px;">
            <?php foreach ($nav as $k => $label): ?>
                <a href="index.php?page=<?= $k ?>" class="<?= $page === $k ? 'on' : '' ?>">&gt; <?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MAIN -->
    <div>
    <?php foreach ($flashes as $f): ?>
        <div class="msg <?= $f[0] === 'ok' ? 'ok' : 'err' ?>"><?= h($f[1]) ?></div>
    <?php endforeach; ?>

<?php /* ===================== HOME ===================== */ if ($page === 'home'):
    $sel = $_GET['table'] ?? '';
    if (!in_array($sel, $tables, true)) $sel = in_array('Concert', $tables) ? 'Concert' : ($tables[0] ?? '');
    $views = listViews($conn);
    $viewName = $_GET['view'] ?? '';
?>
    <div class="win"><h2>Saved Queries</h2><div class="body">
        <?php queryList($conn, 'home', false, $sel); ?>
    </div></div>
    <?php
    if ($runId && ($q = getSavedQuery($conn, $runId))) {
        resultWin($q['label'], $q['description'], $q['sql_text'], runSql($conn, $q['sql_text']));
    }
    ?>

    <div class="win"><h2>Saved Views</h2><div class="body">
        <div class="sunken"><table class="grid">
            <tr><th>View</th><th>Actions</th></tr>
            <?php foreach ($views as $v): ?>
                <tr><td><?= h($v) ?></td><td class="act"><a class="btn" href="index.php?page=home&view=<?= urlencode($v) ?>&table=<?= urlencode($sel) ?>#results">Open</a></td></tr>
            <?php endforeach; ?>
            <?php if (!$views): ?><tr><td colspan="2">No views.</td></tr><?php endif; ?>
        </table></div>
    </div></div>
    <?php
    if ($viewName && in_array(strtolower($viewName), array_map('strtolower', $views)) && ident($viewName)) {
        resultWin("View: $viewName", "Current contents of the view.", "SELECT * FROM `$viewName`;", runSql($conn, "SELECT * FROM `$viewName`"));
    }
    ?>

    <div class="win"><h2>Tables</h2><div class="body">
        <form method="get" action="index.php" class="row">
            <input type="hidden" name="page" value="home">
            <span>Select table:</span>
            <select name="table" onchange="this.form.submit()">
                <?php foreach ($tables as $t): ?><option <?= $t === $sel ? 'selected' : '' ?>><?= h($t) ?></option><?php endforeach; ?>
            </select>
            <button class="btn">Go</button>
        </form>
    </div></div>
    <?php if ($sel) recordsPanel($conn, $sel, 'home'); ?>

<?php /* ===================== TABLES ===================== */ elseif ($page === 'tables'):
    $sel = $_GET['table'] ?? '';
    if (!in_array($sel, $tables, true)) $sel = '';
?>
    <div class="win"><h2>All Tables</h2><div class="body">
        <div class="sunken"><table class="grid">
            <tr><th>Table</th><th>Columns</th><th>Rows</th><th>Actions</th></tr>
            <?php foreach ($tables as $t):
                $n = (int)$conn->query("SELECT COUNT(*) AS c FROM `$t`")->fetch_assoc()['c'];
                $nc = count(getCols($conn, $t)[0]); ?>
                <tr<?= $t === $sel ? ' style="background:#ffffa0"' : '' ?>>
                    <td><?= h($t) ?></td><td><?= $nc ?></td><td><?= $n ?></td>
                    <td class="act">
                        <a class="btn" href="index.php?page=tables&table=<?= urlencode($t) ?>#rec">Open</a>
                        <form method="post" action="index.php" onsubmit="return confirm('DROP table <?= h($t) ?> and all its data?');">
                            <input type="hidden" name="do" value="table_drop"><input type="hidden" name="table" value="<?= h($t) ?>">
                            <button class="btn danger">Drop</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table></div>
    </div></div>

    <div class="win"><h2>New Table</h2><div class="body">
        <form method="post" action="index.php">
            <input type="hidden" name="do" value="table_create">
            <p class="desc">Enter a full CREATE TABLE statement.</p>
            <textarea name="ddl" rows="5" placeholder="CREATE TABLE Example (id INT PRIMARY KEY, name VARCHAR(100));"></textarea>
            <p><button class="btn">Create Table</button></p>
        </form>
    </div></div>

    <?php if ($sel) recordsPanel($conn, $sel, 'tables'); ?>

<?php /* ===================== QUERIES ===================== */ elseif ($page === 'queries'):
    $editQ = null;
    if (isset($_GET['edit'])) $editQ = getSavedQuery($conn, (int)$_GET['edit']);
    if ($adhoc !== null) {
        $fv = ['query_id' => (int)($_POST['query_id'] ?? 0), 'label' => $_POST['label'] ?? '', 'description' => $_POST['description'] ?? '', 'sql_text' => $adhocSql];
    } elseif ($editQ) {
        $fv = $editQ;
    } else {
        $fv = ['query_id' => 0, 'label' => '', 'description' => '', 'sql_text' => ''];
    }
?>
    <div class="win"><h2>Saved Queries</h2><div class="body">
        <?php queryList($conn, 'queries', true, ''); ?>
    </div></div>
    <?php
    if ($adhoc !== null) {
        resultWin('Result (not saved)', '', $adhocSql, $adhoc);
    } elseif ($runId && ($q = getSavedQuery($conn, $runId))) {
        resultWin($q['label'], $q['description'], $q['sql_text'], runSql($conn, $q['sql_text']));
    }
    ?>
    <div class="win" id="form"><h2><?= $fv['query_id'] ? 'Edit Query #' . (int)$fv['query_id'] : 'New Query' ?></h2><div class="body">
        <form method="post" action="index.php?page=queries">
            <input type="hidden" name="query_id" value="<?= (int)$fv['query_id'] ?>">
            <div class="field"><span>Name</span><input type="text" name="label" value="<?= h($fv['label']) ?>"></div>
            <div class="field"><span>Description</span><input type="text" name="description" value="<?= h($fv['description']) ?>"></div>
            <div class="field"><span>SQL</span><textarea name="sql_text" rows="8"><?= h($fv['sql_text']) ?></textarea></div>
            <button class="btn" name="do" value="query_save">Save</button>
            <button class="btn" name="do" value="query_run">Run only (don't save)</button>
            <?php if ($fv['query_id']): ?><a class="btn" href="index.php?page=queries">Cancel</a><?php endif; ?>
        </form>
    </div></div>

<?php /* ===================== VIEWS ===================== */ elseif ($page === 'views'):
    $views = listViews($conn);
    $viewName = $_GET['view'] ?? '';
    $editV = $_GET['edit'] ?? '';
    $fvName = ''; $fvSql = ''; $fvOrig = '';
    if ($editV && in_array(strtolower($editV), array_map('strtolower', $views)) && ident($editV)) {
        $st = $conn->prepare("SELECT VIEW_DEFINITION FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $st->bind_param("s", $editV);
        $st->execute();
        $d = $st->get_result()->fetch_assoc();
        $fvName = $editV; $fvOrig = $editV; $fvSql = $d['VIEW_DEFINITION'] ?? '';
    }
?>
    <div class="win"><h2>All Views</h2><div class="body">
        <div class="sunken"><table class="grid">
            <tr><th>View</th><th>Actions</th></tr>
            <?php foreach ($views as $v): ?>
                <tr><td><?= h($v) ?></td><td class="act">
                    <a class="btn" href="index.php?page=views&view=<?= urlencode($v) ?>#results">Open</a>
                    <a class="btn" href="index.php?page=views&edit=<?= urlencode($v) ?>#form">Edit</a>
                    <form method="post" action="index.php" onsubmit="return confirm('Drop view <?= h($v) ?>?');">
                        <input type="hidden" name="do" value="view_drop"><input type="hidden" name="name" value="<?= h($v) ?>">
                        <button class="btn danger">Drop</button>
                    </form>
                </td></tr>
            <?php endforeach; ?>
            <?php if (!$views): ?><tr><td colspan="2">No views.</td></tr><?php endif; ?>
        </table></div>
    </div></div>
    <?php
    if ($viewName && in_array(strtolower($viewName), array_map('strtolower', $views)) && ident($viewName)) {
        resultWin("View: $viewName", "Current contents of the view.", "SELECT * FROM `$viewName`;", runSql($conn, "SELECT * FROM `$viewName`"));
    }
    ?>
    <div class="win" id="form"><h2><?= $fvOrig ? 'Edit View: ' . h($fvOrig) : 'New View' ?></h2><div class="body">
        <form method="post" action="index.php">
            <input type="hidden" name="do" value="view_save">
            <input type="hidden" name="orig" value="<?= h($fvOrig) ?>">
            <div class="field"><span>View name</span><input type="text" name="name" value="<?= h($fvName) ?>" <?= $fvOrig ? 'readonly' : '' ?>></div>
            <div class="field"><span>SELECT statement</span><textarea name="sql" rows="8" placeholder="SELECT ... FROM ..."><?= h($fvSql) ?></textarea></div>
            <button class="btn"><?= $fvOrig ? 'Save changes' : 'Create view' ?></button>
            <?php if ($fvOrig): ?><a class="btn" href="index.php?page=views">Cancel</a><?php endif; ?>
        </form>
    </div></div>

<?php /* ===================== TRIGGERS ===================== */ elseif ($page === 'triggers'):
    $trigs = listTriggers($conn);
    $editT = ident($_GET['edit'] ?? '');
    $ddlText = ''; $origT = '';
    if ($editT) {
        try {
            $row = $conn->query("SHOW CREATE TRIGGER `$editT`")->fetch_assoc();
            $ddlText = preg_replace('/DEFINER=`[^`]*`@`[^`]*`\s*/', '', $row['SQL Original Statement'] ?? '');
            $origT = $editT;
        } catch (mysqli_sql_exception $e) {}
    }
?>
    <div class="win"><h2>All Triggers</h2><div class="body">
        <div class="sunken"><table class="grid">
            <tr><th>Name</th><th>When</th><th>Table</th><th>Body</th><th>Actions</th></tr>
            <?php foreach ($trigs as $t): ?>
                <tr>
                    <td><?= h($t['TRIGGER_NAME']) ?></td>
                    <td><?= h($t['ACTION_TIMING'] . ' ' . $t['EVENT_MANIPULATION']) ?></td>
                    <td><?= h($t['EVENT_OBJECT_TABLE']) ?></td>
                    <td class="wrap"><details><summary>show</summary><pre><?= h($t['ACTION_STATEMENT']) ?></pre></details></td>
                    <td class="act">
                        <a class="btn" href="index.php?page=triggers&edit=<?= urlencode($t['TRIGGER_NAME']) ?>#form">Edit</a>
                        <form method="post" action="index.php" onsubmit="return confirm('Drop trigger <?= h($t['TRIGGER_NAME']) ?>?');">
                            <input type="hidden" name="do" value="trigger_drop"><input type="hidden" name="name" value="<?= h($t['TRIGGER_NAME']) ?>">
                            <button class="btn danger">Drop</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$trigs): ?><tr><td colspan="5">No triggers.</td></tr><?php endif; ?>
        </table></div>
    </div></div>

    <div class="win" id="form"><h2><?= $origT ? 'Edit Trigger: ' . h($origT) : 'New Trigger' ?></h2><div class="body">
        <form method="post" action="index.php">
            <input type="hidden" name="do" value="trigger_save">
            <input type="hidden" name="orig" value="<?= h($origT) ?>">
            <p class="desc">Paste the full CREATE TRIGGER statement. No DELIMITER lines needed. Editing drops and recreates the trigger; if the new version fails, the original is restored.</p>
            <textarea name="ddl" rows="12" placeholder="CREATE TRIGGER my_trigger&#10;BEFORE INSERT ON Booking&#10;FOR EACH ROW&#10;BEGIN&#10;    ...&#10;END"><?= h($ddlText) ?></textarea>
            <p><button class="btn"><?= $origT ? 'Save changes' : 'Create trigger' ?></button>
            <?php if ($origT): ?><a class="btn" href="index.php?page=triggers">Cancel</a><?php endif; ?></p>
        </form>
    </div></div>
<?php endif; ?>

    </div>
</div>
</body>
</html>