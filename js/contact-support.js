(() => {
    'use strict';

    const phone =
        document.querySelector(
            '[data-support-phone]'
        );

    const preferred =
        document.querySelector(
            '[data-support-preferred-contact]'
        );

    const phoneHelp =
        document.querySelector(
            '[data-support-phone-help]'
        );

    if (!phone || !preferred) {
        return;
    }

    const formatUsPhone = (
        value
    ) => {
        const original =
            String(value || '');

        const trimmed =
            original.trim();

        if (
            trimmed.startsWith('+')
            && !trimmed.startsWith('+1')
        ) {
            return original;
        }

        let digits =
            original.replace(
                /\D+/g,
                ''
            );

        if (
            digits.length === 11
            && digits.startsWith('1')
        ) {
            digits =
                digits.slice(1);
        }

        if (digits.length > 10) {
            digits =
                digits.slice(0, 10);
        }

        if (digits.length <= 3) {
            return digits;
        }

        if (digits.length <= 6) {
            return (
                '('
                + digits.slice(0, 3)
                + ') '
                + digits.slice(3)
            );
        }

        return (
            '('
            + digits.slice(0, 3)
            + ') '
            + digits.slice(3, 6)
            + '-'
            + digits.slice(6)
        );
    };

    const syncRequirement = () => {
        const needsPhone =
            preferred.value === 'text'
            || preferred.value === 'phone';

        phone.required =
            needsPhone;

        if (phoneHelp) {
            phoneHelp.textContent =
                needsPhone
                    ? 'Required for your selected contact method.'
                    : 'Optional for email replies. Required if you prefer a text or phone call.';
        }
    };

    phone.addEventListener(
        'input',
        () => {
            phone.value =
                formatUsPhone(
                    phone.value
                );
        }
    );

    preferred.addEventListener(
        'change',
        syncRequirement
    );

    syncRequirement();
})();
