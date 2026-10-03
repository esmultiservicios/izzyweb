<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_permission('sections.manage');

$pdo = db();

// IZZY landing catalog. This keeps existing installations aligned with the
// actual public sections even before the cumulative SQL package is executed.
$izzySectionDefaults = [
    ['inicio','Inicio','inicio','Inicio',1,'link',10,1],
    ['soluciones','Soluciones','soluciones','Soluciones',1,'link',20,1],
    ['modalidades','Modalidades','modalidades','Modalidades',1,'link',30,1],
    ['planes','Planes','planes','Planes',1,'link',40,1],
    ['sistema','El sistema','sistema','El sistema',1,'link',50,1],
    ['ubicacion','Ubicación','ubicacion','Ubicación',1,'link',60,1],
    ['contacto','Quiero IZZY','contacto','Quiero IZZY',1,'cta',70,1],
];
try {
    $pdo->beginTransaction();
    $legacyKeys = ['home','intro','about','services','videos','gallery','areas','tips','estimate','contact'];
    $placeholders = implode(',', array_fill(0, count($legacyKeys), '?'));
    $cleanup = $pdo->prepare("DELETE FROM site_sections WHERE section_key IN ($placeholders)");
    $cleanup->execute($legacyKeys);

    $seed = $pdo->prepare(
        'INSERT INTO site_sections
         (section_key,label,anchor_id,navigation_label,show_in_navigation,navigation_style,sort_order,active)
         VALUES (?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
           label=VALUES(label), anchor_id=VALUES(anchor_id),
           navigation_label=COALESCE(NULLIF(navigation_label,\'\'),VALUES(navigation_label))'
    );
    foreach ($izzySectionDefaults as $row) {
        $seed->execute($row);
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $keys = array_values($_POST['section_key'] ?? []);
    $activeSections = $_POST['active'] ?? [];
    $navigationSections = $_POST['show_in_navigation'] ?? [];
    $labels = $_POST['label'] ?? [];
    $navigationLabels = $_POST['navigation_label'] ?? [];
    $navigationStyles = $_POST['navigation_style'] ?? [];

    try {
        $pdo->beginTransaction();
        $statement = $pdo->prepare(
            'UPDATE site_sections
             SET label = ?, navigation_label = ?, navigation_style = ?,
                 sort_order = ?, active = ?, show_in_navigation = ?
             WHERE section_key = ?'
        );

        $position = 10;
        foreach ($keys as $key) {
            $key = (string) $key;
            if (!preg_match('/^[a-z0-9_-]{1,60}$/', $key)) {
                throw new RuntimeException('A section identifier is invalid.');
            }

            $label = trim((string) ($labels[$key] ?? ''));
            $navigationLabel = trim((string) ($navigationLabels[$key] ?? ''));
            $navigationStyle = ($navigationStyles[$key] ?? 'link') === 'cta' ? 'cta' : 'link';

            if ($label === '') {
                throw new RuntimeException('Every section needs an administrator label.');
            }
            if (isset($navigationSections[$key]) && $navigationLabel === '') {
                $navigationLabel = $label;
            }

            $statement->execute([
                substr($label, 0, 120),
                substr($navigationLabel, 0, 120),
                $navigationStyle,
                $position,
                isset($activeSections[$key]) ? 1 : 0,
                isset($navigationSections[$key]) ? 1 : 0,
                $key,
            ]);
            $position += 10;
        }

        $pdo->commit();
        log_activity('sections_update', 'Updated public website section layout and navigation.');
        flash('success', 'Section order, visibility and navigation saved.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $exception->getMessage());
    }

    header('Location: sections.php');
    exit;
}

$rows = $pdo->query('SELECT * FROM site_sections ORDER BY sort_order, section_key')->fetchAll();
$pageTitle = 'Section Manager';
$active = 'sections';
require __DIR__ . '/_header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">VISUAL STRUCTURE</p>
        <h1>Landing page sections</h1>
        <p class="muted">Control the real public order, visibility and menu placement from any device.</p>
    </div>
    <a class="button secondary" href="../?preview=1" target="_blank" rel="noopener">Preview website</a>
</div>

<form method="post" class="section-manager-form">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <div class="section-manager-help" role="note">
        <strong>How ordering works</strong>
        <span>Drag a card, or use Move up and Move down. Saving renumbers every section automatically.</span>
    </div>

    <div class="section-sort-list" data-sortable-list>
        <?php foreach ($rows as $row): ?>
            <?php $key = (string) $row['section_key']; ?>
            <article class="section-sort-card" draggable="true" data-section-card>
                <button
                    class="drag-handle"
                    type="button"
                    aria-label="Drag <?= h($row['label']) ?> to reorder"
                    title="Drag to reorder"
                >⋮⋮</button>

                <input type="hidden" name="section_key[]" value="<?= h($key) ?>">
                <input type="hidden" name="sort_order[]" value="<?= (int) $row['sort_order'] ?>" data-sort-order>

                <div class="section-sort-main">
                    <div class="section-sort-heading">
                        <label>
                            <span>Administrator label</span>
                            <input name="label[<?= h($key) ?>]" value="<?= h($row['label']) ?>" maxlength="120" required>
                        </label>
                        <small>
                            <code>#<?= h($key) ?></code>
                            <span>Position <b data-order-label><?= (int) $row['sort_order'] ?></b></span>
                        </small>
                    </div>

                    <div class="section-navigation-settings">
                        <label>
                            <span>Menu label</span>
                            <input
                                name="navigation_label[<?= h($key) ?>]"
                                value="<?= h($row['navigation_label'] ?? $row['label']) ?>"
                                maxlength="120"
                            >
                        </label>
                        <label>
                            <span>Menu style</span>
                            <select name="navigation_style[<?= h($key) ?>]">
                                <option value="link" <?= ($row['navigation_style'] ?? 'link') === 'link' ? 'selected' : '' ?>>Standard link</option>
                                <option value="cta" <?= ($row['navigation_style'] ?? 'link') === 'cta' ? 'selected' : '' ?>>Primary action</option>
                            </select>
                        </label>
                    </div>
                </div>

                <div class="section-sort-controls">
                    <div class="section-move-actions" aria-label="Reorder <?= h($row['label']) ?>">
                        <button class="button secondary small" type="button" data-move-section="up">↑ Move up</button>
                        <button class="button secondary small" type="button" data-move-section="down">↓ Move down</button>
                    </div>

                    <label class="premium-switch compact">
                        <input
                            type="checkbox"
                            name="active[<?= h($key) ?>]"
                            <?= (int) $row['active'] === 1 ? 'checked' : '' ?>
                            data-section-visibility
                        >
                        <span class="switch-ui"></span>
                        <span>
                            <b data-section-visibility-label><?= (int) $row['active'] === 1 ? 'Visible' : 'Hidden' ?></b>
                            <small>Public section</small>
                        </span>
                    </label>

                    <label class="premium-switch compact">
                        <input
                            type="checkbox"
                            name="show_in_navigation[<?= h($key) ?>]"
                            <?= (int) ($row['show_in_navigation'] ?? 0) === 1 ? 'checked' : '' ?>
                        >
                        <span class="switch-ui"></span>
                        <span>
                            <b>Public menu</b>
                            <small>Show as navigation item</small>
                        </span>
                    </label>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="savebar section-manager-savebar">
        <button type="submit">Save section layout</button>
    </div>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
