(function(){
    async function fetchJson(url, options) {
        /**  try {
            if (options && options.body && typeof FormData !== 'undefined' && options.body instanceof FormData) {
                for (const e of options.body.entries()) console.debug('AJAX POST', e[0], e[1]);
            }
        } catch (err) {} */

        const resp = await fetch(url, options || {});
        const txt = await resp.text();
        if (resp.redirected || (resp.status >= 300 && resp.status < 400)) {
            const loc = resp.headers.get('Location') || '(location inconnue)';
            throw new Error('Requête redirigée (HTTP ' + resp.status + '). Contenu: ' + txt.slice(0, 800));
        }
        if (!resp.ok) {
            try {
                const j = JSON.parse(txt);
                throw new Error(j.message || 'Erreur réseau');
            } catch (e) {
                throw new Error(txt || 'Erreur réseau');
            }
        }
        const trimmed = txt.trim();
        if (trimmed === '') return {};
        try {
            return JSON.parse(trimmed);
        } catch (e) {
            const snippet = trimmed.slice(0, 300);
            throw new Error('Réponse invalide du serveur: ' + snippet);
        }
    }

    function escapeHtml(str) {
        return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    window.fetchJson = fetchJson;
    window.escapeHtml = escapeHtml;
})();