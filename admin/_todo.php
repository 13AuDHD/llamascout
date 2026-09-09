<?php

declare(strict_types=1);

$todoItems = [];
$todoOpenCount = 0;
$todoSchemaReady = true;
$todoLoadError = '';

try {
    $todoStmt = $db->query(
        "SELECT
            id,
            task,
            is_completed,
            created_by,
            created_at,
            completed_at,
            updated_at
         FROM admin_todos
         ORDER BY
            is_completed ASC,
            CASE WHEN is_completed = 0 THEN created_at END DESC,
            CASE WHEN is_completed = 1 THEN completed_at END DESC,
            id DESC"
    );

    $todoItems = $todoStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($todoItems as $todoItem) {
        if (empty($todoItem['is_completed'])) {
            $todoOpenCount++;
        }
    }
} catch (Throwable $exception) {
    $todoSchemaReady = false;
    $todoLoadError = 'The Site To-Do database table has not been installed yet.';
}

$todoCsrfToken = moderation_csrf_token();

function admin_todo_date_label(?string $value): string
{
    if (!$value) {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))
            ->format('M j');
    } catch (Throwable) {
        return '';
    }
}
?>

<section
    class="admin-panel admin-todo-panel"
    data-admin-todo
    data-endpoint="/todo-action.php"
    data-csrf="<?= moderation_e($todoCsrfToken) ?>"
>
    <header class="admin-panel-header">
        <div>
            <p>Site Work</p>
            <h2>Site To-Do</h2>
        </div>

        <span class="admin-todo-open-count" data-todo-open-count>
            <?= $todoOpenCount ?> open
        </span>
    </header>

    <?php if (!$todoSchemaReady): ?>

        <div class="admin-todo-schema-warning">
            <i class="fa-solid fa-database" aria-hidden="true"></i>
            <div>
                <strong>To-Do list needs its database table.</strong>
                <span><?= moderation_e($todoLoadError) ?></span>
            </div>
        </div>

    <?php else: ?>

        <form class="admin-todo-add" data-todo-add-form>
            <label class="visually-hidden" for="admin-todo-new-task">
                What needs done?
            </label>

            <input
                id="admin-todo-new-task"
                type="text"
                name="task"
                maxlength="500"
                placeholder="What needs done?"
                autocomplete="off"
                data-todo-input
            >

            <button type="submit">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span>Add</span>
            </button>
        </form>

        <p class="admin-todo-feedback" data-todo-feedback aria-live="polite"></p>

        <div
            class="admin-todo-list"
            data-todo-list
            <?= !$todoItems ? 'hidden' : '' ?>
        >
            <?php foreach ($todoItems as $todoItem): ?>
                <?php
                $todoId = (int) $todoItem['id'];
                $isCompleted = !empty($todoItem['is_completed']);
                $dateLabel = $isCompleted
                    ? admin_todo_date_label((string) ($todoItem['completed_at'] ?? ''))
                    : admin_todo_date_label((string) ($todoItem['created_at'] ?? ''));
                ?>
                <article
                    class="admin-todo-item<?= $isCompleted ? ' is-completed' : '' ?>"
                    data-todo-item
                    data-todo-id="<?= $todoId ?>"
                    data-completed="<?= $isCompleted ? '1' : '0' ?>"
                >
                    <button
                        class="admin-todo-toggle"
                        type="button"
                        data-todo-toggle
                        aria-label="<?= $isCompleted ? 'Reopen task' : 'Mark task complete' ?>"
                        aria-pressed="<?= $isCompleted ? 'true' : 'false' ?>"
                    >
                        <i
                            class="<?= $isCompleted
                                ? 'fa-solid fa-circle-check'
                                : 'fa-regular fa-circle' ?>"
                            aria-hidden="true"
                        ></i>
                    </button>

                    <div class="admin-todo-copy">
                        <button
                            class="admin-todo-task"
                            type="button"
                            data-todo-edit
                            title="Tap to edit"
                        >
                            <?= moderation_e((string) $todoItem['task']) ?>
                        </button>

                        <?php if ($dateLabel !== ''): ?>
                            <small data-todo-date>
                                <?= $isCompleted ? 'Completed ' : 'Added ' ?>
                                <?= moderation_e($dateLabel) ?>
                            </small>
                        <?php else: ?>
                            <small data-todo-date></small>
                        <?php endif; ?>
                    </div>

                    <button
                        class="admin-todo-delete"
                        type="button"
                        data-todo-delete
                        aria-label="Remove task"
                        title="Tap twice to remove"
                    >
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        <span class="visually-hidden">Remove task</span>
                    </button>
                </article>
            <?php endforeach; ?>
        </div>

        <div
            class="admin-todo-empty"
            data-todo-empty
            <?= $todoItems ? 'hidden' : '' ?>
        >
            <i class="fa-solid fa-list-check" aria-hidden="true"></i>
            <strong>Nothing left on the list.</strong>
            <span>Suspicious, but enjoy it while it lasts.</span>
        </div>

    <?php endif; ?>
</section>
