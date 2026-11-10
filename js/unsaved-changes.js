(function () {
    const revisions = new WeakMap();
    let revision = 0;
    const selector = 'form[data-glpinewentity-track="true"]';

    function mark(form) {
        if (!form || !form.matches(selector)) return;
        revisions.set(form, ++revision);
        setHasUnsavedChanges(true);
    }

    function hasOtherChanges(form) {
        return Array.from(document.querySelectorAll(selector)).some(function (other) {
            return other !== form && revisions.has(other);
        });
    }

    function confirmOtherChanges(form) {
        return !hasOtherChanges(form) || confirm(
            'Há alterações não salvas em outras abas. Ao salvar esta aba, elas serão perdidas. Deseja continuar?'
        );
    }

    function snapshot() {
        return revision;
    }

    function clearThrough(savedRevision) {
        let remaining = false;
        document.querySelectorAll(selector).forEach(function (form) {
            const formRevision = revisions.get(form);
            if (formRevision !== undefined && formRevision <= savedRevision) {
                revisions.delete(form);
            } else if (formRevision !== undefined) {
                remaining = true;
            }
        });
        setHasUnsavedChanges(remaining);
    }

    $(document).on('input change', selector + ' input, ' + selector + ' textarea, ' + selector + ' select', function () {
        mark(this.closest('form'));
    });
    $(document).on('tinyMCEChange', function (event, editorEvent) {
        const element = editorEvent && editorEvent.target && editorEvent.target.targetElm;
        if (element) mark(element.closest('form'));
    });
    $(document).on('click', selector + ' .illustration-wrapper [data-glpi-icon-picker-value], ' +
        selector + ' .illustration-wrapper [data-glpi-icon-picker-use-custom-file]', function () {
        mark(this.closest('form'));
    });

    window.glpinewentityUnsaved = { mark, confirmOtherChanges, snapshot, clearThrough };
})();
