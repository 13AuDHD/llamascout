(() => {
    'use strict';

    const module = document.querySelector('[data-admin-todo]');

    if (!module) {
        return;
    }

    const endpoint = module.dataset.endpoint || '/todo-action.php';
    const csrf = module.dataset.csrf || '';

    const form = module.querySelector('[data-todo-add-form]');
    const input = module.querySelector('[data-todo-input]');
    const list = module.querySelector('[data-todo-list]');
    const empty = module.querySelector('[data-todo-empty]');
    const count = module.querySelector('[data-todo-open-count]');
    const feedback = module.querySelector('[data-todo-feedback]');

    let armedDelete = null;
    let armedDeleteTimer = null;


    const escapeHtml = (value) => {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    };


    const setFeedback = (message = '', isError = false) => {
        if (!feedback) {
            return;
        }

        feedback.textContent = message;
        feedback.classList.toggle('is-error', isError);
    };


    const request = async (payload) => {
        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                ...payload,
                csrf_token: csrf
            })
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.ok !== true) {
            throw new Error(
                data.error || 'The Site To-Do list could not be updated.'
            );
        }

        return data;
    };


    const items = () =>
        [...module.querySelectorAll('[data-todo-item]')];


    const updateCount = () => {
        const open = items().filter(
            (item) => item.dataset.completed !== '1'
        ).length;

        if (count) {
            count.textContent = `${open} open`;
        }

        if (list) {
            list.hidden = items().length === 0;
        }

        if (empty) {
            empty.hidden = items().length !== 0;
        }
    };


    const moveItem = (item) => {
        if (!list) {
            return;
        }

        if (item.dataset.completed === '1') {
            list.appendChild(item);
            return;
        }

        const firstCompleted = items().find(
            (candidate) =>
                candidate !== item &&
                candidate.dataset.completed === '1'
        );

        if (firstCompleted) {
            list.insertBefore(item, firstCompleted);
        } else {
            list.prepend(item);
        }
    };


    const applyCompletedState = (
        item,
        completed,
        dateLabel = ''
    ) => {
        item.dataset.completed = completed ? '1' : '0';
        item.classList.toggle('is-completed', completed);

        const toggle = item.querySelector('[data-todo-toggle]');
        const icon = toggle?.querySelector('i');
        const date = item.querySelector('[data-todo-date]');

        if (toggle) {
            toggle.setAttribute(
                'aria-pressed',
                completed ? 'true' : 'false'
            );

            toggle.setAttribute(
                'aria-label',
                completed ? 'Reopen task' : 'Mark task complete'
            );
        }

        if (icon) {
            icon.className = completed
                ? 'fa-solid fa-circle-check'
                : 'fa-regular fa-circle';
        }

        if (date) {
            date.textContent = dateLabel;
        }

        moveItem(item);
        updateCount();
    };


    const createItem = (task) => {
        const article = document.createElement('article');

        article.className = 'admin-todo-item';
        article.dataset.todoItem = '';
        article.dataset.todoId = String(task.id);
        article.dataset.completed = '0';

        article.innerHTML = `
            <button
                class="admin-todo-toggle"
                type="button"
                data-todo-toggle
                aria-label="Mark task complete"
                aria-pressed="false"
            >
                <i class="fa-regular fa-circle" aria-hidden="true"></i>
            </button>

            <div class="admin-todo-copy">
                <button
                    class="admin-todo-task"
                    type="button"
                    data-todo-edit
                    title="Tap to edit"
                >${escapeHtml(task.task)}</button>

                <small data-todo-date>${escapeHtml(task.date_label || '')}</small>
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
        `;

        return article;
    };


    const disarmDelete = () => {
        if (armedDeleteTimer) {
            clearTimeout(armedDeleteTimer);
            armedDeleteTimer = null;
        }

        if (armedDelete) {
            armedDelete.classList.remove('is-armed');
            armedDelete.setAttribute('aria-label', 'Remove task');
            armedDelete.title = 'Tap twice to remove';
            armedDelete = null;
        }
    };


    const startInlineEdit = (item) => {
        const button = item.querySelector('[data-todo-edit]');

        if (!button || item.querySelector('[data-todo-edit-input]')) {
            return;
        }

        const original = button.textContent.trim();
        const editor = document.createElement('input');

        editor.type = 'text';
        editor.maxLength = 500;
        editor.value = original;
        editor.className = 'admin-todo-edit-input';
        editor.dataset.todoEditInput = '';

        button.replaceWith(editor);
        editor.focus();
        editor.select();

        let finished = false;

        const finish = async (save) => {
            if (finished) {
                return;
            }

            finished = true;

            const next = editor.value.trim();
            const replacement = document.createElement('button');

            replacement.type = 'button';
            replacement.className = 'admin-todo-task';
            replacement.dataset.todoEdit = '';
            replacement.title = 'Tap to edit';
            replacement.textContent =
                save && next !== '' ? next : original;

            editor.replaceWith(replacement);

            if (!save || next === '' || next === original) {
                return;
            }

            try {
                await request({
                    action: 'edit',
                    id: Number(item.dataset.todoId),
                    task: next
                });

                setFeedback('Task updated.');
            } catch (error) {
                replacement.textContent = original;
                setFeedback(error.message, true);
            }
        };

        editor.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                finish(true);
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                finish(false);
            }
        });

        editor.addEventListener('blur', () => finish(true), {
            once: true
        });
    };


    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const task = input?.value.trim() || '';

        if (!task) {
            setFeedback('Enter a task first.', true);
            input?.focus();
            return;
        }

        const submit = form.querySelector('button[type="submit"]');

        submit?.setAttribute('disabled', '');

        try {
            const data = await request({
                action: 'add',
                task
            });

            const item = createItem(data.task);

            list?.prepend(item);

            if (input) {
                input.value = '';
                input.focus();
            }

            updateCount();
            setFeedback('Task added.');
        } catch (error) {
            setFeedback(error.message, true);
        } finally {
            submit?.removeAttribute('disabled');
        }
    });


    module.addEventListener('click', async (event) => {
        const item = event.target.closest('[data-todo-item]');

        if (!item) {
            disarmDelete();
            return;
        }

        const id = Number(item.dataset.todoId || 0);

        if (event.target.closest('[data-todo-edit]')) {
            disarmDelete();
            startInlineEdit(item);
            return;
        }

        const toggle = event.target.closest('[data-todo-toggle]');

        if (toggle) {
            disarmDelete();
            toggle.setAttribute('disabled', '');

            try {
                const data = await request({
                    action: 'toggle',
                    id
                });

                applyCompletedState(
                    item,
                    data.is_completed === true,
                    data.date_label || ''
                );

                setFeedback(
                    data.is_completed
                        ? 'Task completed.'
                        : 'Task reopened.'
                );
            } catch (error) {
                setFeedback(error.message, true);
            } finally {
                toggle.removeAttribute('disabled');
            }

            return;
        }

        const deleteButton =
            event.target.closest('[data-todo-delete]');

        if (!deleteButton) {
            disarmDelete();
            return;
        }

        if (armedDelete !== deleteButton) {
            disarmDelete();

            armedDelete = deleteButton;
            deleteButton.classList.add('is-armed');
            deleteButton.setAttribute(
                'aria-label',
                'Tap again to remove task'
            );
            deleteButton.title = 'Tap again to remove';

            armedDeleteTimer = window.setTimeout(
                disarmDelete,
                4000
            );

            return;
        }

        deleteButton.setAttribute('disabled', '');

        try {
            await request({
                action: 'delete',
                id
            });

            item.classList.add('is-removing');

            window.setTimeout(() => {
                item.remove();
                updateCount();
            }, 130);

            setFeedback('Task removed.');
        } catch (error) {
            deleteButton.removeAttribute('disabled');
            setFeedback(error.message, true);
        } finally {
            disarmDelete();
        }
    });


    document.addEventListener('click', (event) => {
        if (
            armedDelete &&
            !event.target.closest('[data-todo-delete]')
        ) {
            disarmDelete();
        }
    });

    updateCount();
})();
