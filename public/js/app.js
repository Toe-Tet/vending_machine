// Progressive enhancement only: every rule here is re-checked on the server.
(function () {
    'use strict';

    // Messages and limits mirror src/Validation/ProductValidator.php; change both together.
    var productRules = {
        name: function (value) {
            value = value.trim();
            if (value === '') return 'Enter a name.';
            if (Array.from(value).length > 100) return 'Name must be 100 characters or fewer.';
            return '';
        },
        price: function (value, input) {
            if (input.validity.badInput) return 'Price must be a number.';
            value = value.trim();
            if (value === '') return 'Enter a price.';
            if (!/^-?(\d+(\.\d+)?|\.\d+)$/.test(value)) return 'Price must be a number.';
            // Checked on the digits, not Number(): 0.0001 is positive (but has too many decimals).
            if (value.charAt(0) === '-' || !/[1-9]/.test(value)) return 'Price must be greater than 0.';
            var fraction = (value.split('.')[1] || '').replace(/0+$/, '');
            if (fraction.length > 3) return 'Price can have at most 3 decimal places.';
            if (Number(value) > 999999.999) return 'Price must be 999,999.999 or less.';
            return '';
        },
        quantity_available: function (value, input) {
            if (input.validity.badInput) return 'Quantity must be a whole number.';
            value = value.trim();
            if (value === '') return 'Enter a quantity.';
            if (!/^-?\d+$/.test(value)) return 'Quantity must be a whole number.';
            if (/^-/.test(value) && /[1-9]/.test(value)) return 'Quantity cannot be negative.';
            if (Number(value) > 1000000) return 'Quantity must be 1,000,000 or less.';
            return '';
        }
    };

    function check(input) {
        var rule = productRules[input.name];
        if (!rule) return true;
        var message = rule(input.value, input);
        var slot = input.form.querySelector('[data-error-for="' + input.name + '"]');
        if (slot) slot.textContent = message;
        if (message) {
            input.setAttribute('aria-invalid', 'true');
        } else if (input.hasAttribute('aria-invalid')) {
            input.setAttribute('aria-invalid', 'false');
        }
        return message === '';
    }

    document.querySelectorAll('form.product-form').forEach(function (form) {
        // Our inline messages replace the browser's bubbles; without JS the HTML5 attributes still apply.
        form.noValidate = true;
        var inputs = Array.prototype.filter.call(form.elements, function (el) { return productRules[el.name]; });

        inputs.forEach(function (input) {
            input.addEventListener('blur', function () { check(input); });
            input.addEventListener('input', function () {
                if (input.getAttribute('aria-invalid') === 'true') check(input);
            });
        });

        form.addEventListener('submit', function (event) {
            var firstInvalid = null;
            inputs.forEach(function (input) {
                if (!check(input) && !firstInvalid) firstInvalid = input;
            });
            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus();
            }
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
        });
    });
})();
