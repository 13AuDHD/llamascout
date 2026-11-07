/* Recurring month/day selection, with no calendar year. */
(function () {
    'use strict';
    function setup(picker) {
        var month = picker.querySelector('[data-season-month]');
        var day = picker.querySelector('[data-season-day]');
        var output = picker.querySelector('[data-season-value]');
        if (!month || !day || !output) return;
        function limitDays() {
            var m = Number(month.value);
            var maxDay = m ? new Date(2024, m, 0).getDate() : 31;
            Array.prototype.forEach.call(day.options, function (option) {
                if (!option.value) return;
                option.disabled = Number(option.value) > maxDay;
                option.hidden = option.disabled;
            });
            if (day.value && Number(day.value) > maxDay) day.value = '';
        }
        function readSavedDate() {
            var parts = /^(\d{2})-(\d{2})$/.exec(output.value);
            month.value = parts ? parts[1] : '';
            day.value = parts ? parts[2] : '';
            limitDays();
        }
        function sync() {
            limitDays();
            var value = month.value && day.value ? month.value + '-' + day.value : '';
            if (output.value !== value) {
                output.value = value;
                output.dispatchEvent(new Event('input', { bubbles: true }));
                output.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
        month.addEventListener('change', sync);
        day.addEventListener('change', sync);
        output.addEventListener('change', readSavedDate);
        var form = output.form;
        if (form) {
            form.addEventListener('change', function (event) {
                // The Area editor replaces scoped values before dispatching a form change.
                if (event.target === form) readSavedDate();
            });
        }
        readSavedDate();
    }
    function init() {
        document.querySelectorAll('[data-season-picker]').forEach(setup);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}());
