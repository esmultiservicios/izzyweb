<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_permission('gallery.manage');

$pdo = db();
$error = '';
$submittedProject = null;

function project_text(string $key, int $maximumLength = 0): string
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if ($maximumLength <= 0) {
        return $value;
    }

    return function_exists('mb_substr')
        ? mb_substr($value, 0, $maximumLength)
        : substr($value, 0, $maximumLength);
}

function validated_project_url(string $url): string
{
    if ($url === '') {
        return '';
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
        throw new RuntimeException('Project URL must be a complete HTTP or HTTPS address.');
    }

    return $url;
}

function selected_project_media(PDO $pdo, string $path): string
{
    if ($path === '') {
        return '';
    }

    $statement = $pdo->prepare('SELECT file_path, mime_type FROM media_library WHERE file_path = ? LIMIT 1');
    $statement->execute([$path]);
    $media = $statement->fetch();
    if (!$media) {
        throw new RuntimeException('The selected Media Library image is no longer available.');
    }

    $mimeType = strtolower((string) ($media['mime_type'] ?? ''));
    $extension = strtolower(pathinfo((string) $media['file_path'], PATHINFO_EXTENSION));
    if (($mimeType !== '' && !str_starts_with($mimeType, 'image/')) || !in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        throw new RuntimeException('Select a JPG, PNG or WEBP image from the Media Library.');
    }

    return (string) $media['file_path'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = max(0, (int) ($_POST['id'] ?? 0));

    try {
        if ($action === 'save') {
            $currentProject = null;
            if ($id > 0) {
                $statement = $pdo->prepare('SELECT * FROM gallery WHERE id = ? LIMIT 1');
                $statement->execute([$id]);
                $currentProject = $statement->fetch();
                if (!$currentProject) {
                    throw new RuntimeException('The project could not be found.');
                }
            }

            $submittedProject = [
                'id' => $id,
                'title' => project_text('title', 150),
                'title_es' => project_text('title_es', 150),
                'category' => project_text('category', 120),
                'category_es' => project_text('category_es', 120),
                'description' => project_text('description'),
                'description_es' => project_text('description_es'),
                'project_url' => project_text('project_url', 700),
                'sort_order' => max(-999999, min(999999, (int) ($_POST['sort_order'] ?? 0))),
                'active' => isset($_POST['active']) ? 1 : 0,
                'image_path' => (string) ($currentProject['image_path'] ?? ''),
            ];

            if ($submittedProject['title'] === '') {
                throw new RuntimeException('Project name is required.');
            }

            $submittedProject['project_url'] = validated_project_url($submittedProject['project_url']);

            if (isset($_POST['remove_image'])) {
                $submittedProject['image_path'] = '';
            }

            $mediaPath = selected_project_media($pdo, trim((string) ($_POST['media_path'] ?? '')));
            if ($mediaPath !== '') {
                $submittedProject['image_path'] = $mediaPath;
            }

            $imageError = (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($imageError !== UPLOAD_ERR_NO_FILE) {
                $submittedProject['image_path'] = upload_image($_FILES['image'], 'gallery', 'project', 8);
                media_add($submittedProject['image_path'], $submittedProject['title']);
            }

            $values = [
                $submittedProject['title'],
                $submittedProject['title_es'],
                $submittedProject['category'],
                $submittedProject['category_es'],
                $submittedProject['description'],
                $submittedProject['description_es'],
                $submittedProject['image_path'],
                $submittedProject['project_url'],
                $submittedProject['sort_order'],
                $submittedProject['active'],
            ];

            if ($id > 0) {
                $values[] = $id;
                $pdo->prepare(
                    'UPDATE gallery
                     SET title = ?, title_es = ?, category = ?, category_es = ?,
                         description = ?, description_es = ?, image_path = ?, project_url = ?,
                         sort_order = ?, active = ?
                     WHERE id = ?'
                )->execute($values);
                log_activity('project_update', 'Updated a project or case study.', ['project_id' => $id]);
                flash('success', 'Project updated successfully.');
            } else {
                $pdo->prepare(
                    'INSERT INTO gallery
                     (title, title_es, category, category_es, description, description_es,
                      image_path, project_url, sort_order, active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute($values);
                $newId = (int) $pdo->lastInsertId();
                log_activity('project_create', 'Created a new project or case study.', ['project_id' => $newId]);
                flash('success', 'Project added successfully.');
            }
        } elseif ($action === 'delete' && $id > 0) {
            $statement = $pdo->prepare('SELECT title FROM gallery WHERE id = ? LIMIT 1');
            $statement->execute([$id]);
            $title = $statement->fetchColumn();
            if ($title === false) {
                throw new RuntimeException('The project could not be found.');
            }

            $pdo->prepare('DELETE FROM gallery WHERE id = ?')->execute([$id]);
            log_activity('project_delete', 'Deleted a project or case study.', ['project_id' => $id]);
            flash('success', 'Project deleted successfully.');
        }

        header('Location: gallery.php');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$edit = null;
if ($submittedProject !== null) {
    $edit = $submittedProject;
} elseif (isset($_GET['edit'])) {
    $statement = $pdo->prepare('SELECT * FROM gallery WHERE id = ? LIMIT 1');
    $statement->execute([max(0, (int) $_GET['edit'])]);
    $edit = $statement->fetch() ?: null;
}

$form = $edit ?? [
    'id' => 0,
    'title' => '',
    'title_es' => '',
    'category' => '',
    'category_es' => '',
    'description' => '',
    'description_es' => '',
    'image_path' => '',
    'project_url' => '',
    'sort_order' => 0,
    'active' => 1,
];

$rows = $pdo->query('SELECT * FROM gallery ORDER BY sort_order, id')->fetchAll();
$mediaLibraryImages = $pdo->query(
    "SELECT file_path, title FROM media_library
     WHERE mime_type LIKE 'image/%'
        OR ((mime_type IS NULL OR mime_type = '') AND LOWER(file_path) REGEXP '[.](jpg|jpeg|png|webp)$')
     ORDER BY id DESC LIMIT 100"
)->fetchAll();

$isEditing = (int) ($form['id'] ?? 0) > 0;
$pageTitle = $isEditing ? 'Edit project' : 'Projects / Case Studies';
$active = 'gallery';
require __DIR__ . '/_header.php';
?>

<div class="page-heading projects-page-heading">
    <div>
        <p class="eyebrow">PROJECTS / CASE STUDIES</p>
        <h1>Project manager</h1>
        <p class="muted">Create, publish and maintain reusable project or case-study cards from one clean workspace. The public IZZY showcase uses these records too: image, title, description, order and visibility are fully configurable here.</p>
    </div>
    <?php if ($isEditing): ?>
        <a class="button secondary" href="gallery.php"><?= icon('plus') ?> Add another project</a>
    <?php else: ?>
        <a class="button secondary" href="#project-form"><?= icon('plus') ?> Add project</a>
    <?php endif; ?>
</div>

<?php if ($error !== ''): ?>
    <div class="alert error"><?= h($error) ?></div>
<?php endif; ?>

<section class="panel project-form-panel" id="project-form">
    <div class="panel-heading">
        <div class="panel-icon"><?= icon($isEditing ? 'edit' : 'plus') ?></div>
        <div>
            <h2><?= $isEditing ? 'Edit project / Editar proyecto' : 'Add project / Nuevo proyecto' ?></h2>
            <p>The default-language fields are always available. Spanish translations are optional and ready for bilingual implementations.</p>
        </div>
    </div>

    <div class="project-workflow-strip" aria-label="Project workflow">
        <span><b>1</b> Datos</span><span><b>2</b> Imagen</span><span><b>3</b> Publicación</span>
    </div>

    <form method="post" enctype="multipart/form-data" data-unsaved-form>
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) ($form['id'] ?? 0) ?>">

        <div class="project-form-grid">
            <div class="project-copy-fields">
                <div class="project-form-section-title"><span><?= icon('edit') ?></span><div><strong>Project information</strong><small>Nombre, categoría, descripción y enlace.</small></div></div>
                <div class="two-col">
                    <label>
                        Project name <span class="field-language">Default / EN</span>
                        <input name="title" maxlength="150" required value="<?= h($form['title'] ?? '') ?>">
                    </label>
                    <label>
                        Nombre del proyecto <span class="field-language">ES · optional</span>
                        <input name="title_es" maxlength="150" value="<?= h($form['title_es'] ?? '') ?>">
                    </label>
                </div>

                <div class="two-col">
                    <label>
                        Category <span class="field-language">Default / EN</span>
                        <input name="category" maxlength="120" value="<?= h($form['category'] ?? '') ?>">
                    </label>
                    <label>
                        Categoría <span class="field-language">ES · optional</span>
                        <input name="category_es" maxlength="120" value="<?= h($form['category_es'] ?? '') ?>">
                    </label>
                </div>

                <div class="two-col project-description-grid">
                    <label>
                        Description <span class="field-language">Default / EN</span>
                        <textarea name="description" rows="6" data-rich-text><?= h($form['description'] ?? '') ?></textarea>
                    </label>
                    <label>
                        Descripción <span class="field-language">ES · optional</span>
                        <textarea name="description_es" rows="6" data-rich-text><?= h($form['description_es'] ?? '') ?></textarea>
                    </label>
                </div>

                <div class="two-col">
                    <label>
                        Project URL
                        <input
                            type="url"
                            name="project_url"
                            maxlength="700"
                            inputmode="url"
                            placeholder="https://"
                            value="<?= h($form['project_url'] ?? '') ?>"
                        >
                    </label>
                    <label>
                        Order
                        <input type="number" name="sort_order" min="-999999" max="999999" value="<?= (int) ($form['sort_order'] ?? 0) ?>">
                    </label>
                </div>
            </div>

            <aside class="project-media-fields">
                <div class="project-form-section-title"><span><?= icon('image') ?></span><div><strong>Media & publishing</strong><small>Portada, biblioteca y estado público.</small></div></div>
                <label>
                    Reuse image from Media Library
                    <select name="media_path" data-project-media-select>
                        <option value="">Keep current image / upload new</option>
                        <?php foreach ($mediaLibraryImages as $media): ?>
                            <option value="<?= h($media['file_path']) ?>" data-preview-src="../<?= h($media['file_path']) ?>">
                                <?= h($media['title'] ?: basename($media['file_path'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="project-library-preview" data-project-media-preview hidden>
                    <img src="" alt="Selected Media Library image">
                    <span>Selected Media Library image</span>
                </div>

                <div class="upload-zone project-upload-zone" data-upload-zone tabindex="0">
                    <div class="upload-icon"><?= icon('image') ?></div>
                    <strong>Project image, logo or cover</strong>
                    <small data-upload-name data-empty-label="Drop, paste or choose a JPG, PNG or WEBP image">Drop, paste or choose a JPG, PNG or WEBP image</small>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                    <div class="upload-preview" data-upload-preview></div>
                </div>

                <?php if (!empty($form['image_path'])): ?>
                    <div class="project-current-image">
                        <img src="../<?= h($form['image_path']) ?>" alt="Current project image">
                        <div>
                            <strong>Current image</strong>
                            <button
                                type="button"
                                class="button secondary small"
                                data-preview-src="../<?= h($form['image_path']) ?>"
                                data-preview-caption="<?= h($form['title'] ?? 'Project image') ?>"
                            ><?= icon('eye') ?> Preview</button>
                            <label class="premium-check compact-check">
                                <input type="checkbox" name="remove_image" value="1">
                                <span>Remove image</span>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>

                <label class="premium-switch project-publish-switch">
                    <input type="checkbox" name="active" <?= (int) ($form['active'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <span class="switch-ui" aria-hidden="true"></span>
                    <span>
                        <b>Published</b>
                        <small>Show this project on the public website.</small>
                    </span>
                </label>
            </aside>
        </div>

        <div class="form-actions project-form-actions">
            <button type="submit"><?= icon($isEditing ? 'edit' : 'plus') ?> <?= $isEditing ? 'Update project' : 'Add project' ?></button>
            <?php if ($isEditing): ?>
                <a class="button secondary" href="gallery.php">Cancel editing</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="projects-list-section" aria-labelledby="projects-list-title">
    <div class="projects-list-heading">
        <div>
            <p class="eyebrow">EXISTING PROJECTS</p>
            <h2 id="projects-list-title">Projects and case studies</h2>
        </div>
        <span class="project-count"><?= count($rows) ?> total</span>
    </div>

    <?php if (!$rows): ?>
        <div class="empty-state">
            <?= icon('image') ?>
            <strong>No projects yet</strong>
            <p>Use “Add project / Nuevo proyecto” to create the first record.</p>
        </div>
    <?php else: ?>
        <div class="gallery-editor projects-editor">
            <?php foreach ($rows as $project): ?>
                <?php
                $displayImage = trim((string) ($project['image_path'] ?? ''));
                $description = trim((string) ($project['description'] ?? ''));
                $projectUrl = trim((string) ($project['project_url'] ?? ''));
                if (!preg_match('~^https?://~i', $projectUrl) || filter_var($projectUrl, FILTER_VALIDATE_URL) === false) {
                    $projectUrl = '';
                }
                ?>
                <article class="gallery-item project-admin-card animate-in">
                    <div class="gallery-media">
                        <?php if ($displayImage !== ''): ?>
                            <img src="../<?= h($displayImage) ?>" alt="<?= h($project['title']) ?>">
                            <button
                                type="button"
                                class="zoom-btn"
                                data-preview-src="../<?= h($displayImage) ?>"
                                data-preview-caption="<?= h($project['title']) ?>"
                                aria-label="Preview <?= h($project['title']) ?>"
                            ><?= icon('eye') ?></button>
                        <?php else: ?>
                            <div class="gallery-empty-media">
                                <strong>No image</strong>
                                <span>Add a cover before publishing.</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="project-admin-card-copy">
                        <div class="project-admin-card-title">
                            <div>
                                <?php if (trim((string) ($project['category'] ?? '')) !== ''): ?>
                                    <span class="project-category"><?= h($project['category']) ?></span>
                                <?php endif; ?>
                                <strong><?= h($project['title']) ?></strong>
                            </div>
                            <span class="project-status <?= (int) $project['active'] === 1 ? 'published' : 'draft' ?>">
                                <?= (int) $project['active'] === 1 ? 'Published' : 'Unpublished' ?>
                            </span>
                        </div>

                        <?php if ($description !== ''): ?>
                            <div class="rich-display"><?= cms_sanitize_rich_html((string)$description) ?></div>
                        <?php endif; ?>

                        <div class="project-admin-meta">
                            <span>Order <?= (int) $project['sort_order'] ?></span>
                            <?php if (trim((string) ($project['title_es'] ?? '')) !== ''): ?>
                                <span>ES translation</span>
                            <?php endif; ?>
                            <?php if ($projectUrl !== ''): ?>
                                <a href="<?= h($projectUrl) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external') ?> Open project</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <footer>
                        <a class="button secondary small" href="?edit=<?= (int) $project['id'] ?>#project-form"><?= icon('edit') ?> Edit</a>
                        <form
                            method="post"
                            data-swal-confirm="Delete project?"
                            data-swal-text="This project record will be permanently removed. Uploaded media will remain available in the Media Library."
                            data-swal-confirm-text="Yes, delete project"
                        >
                            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
                            <button type="submit" class="button danger small"><?= icon('trash') ?> Delete</button>
                        </form>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
