/* ── Password visibility toggle ── */
    function togglePw(fieldId, btnId) {
        const field = document.getElementById(fieldId);
        const isText = field.type === 'text';
        field.type = isText ? 'password' : 'text';
        document.getElementById(btnId).style.color = isText ? '' : 'var(--purple-main)';
    }

    /* ── Submit spinner ── */
    document.getElementById('loginForm').addEventListener('submit', function () {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        document.getElementById('btnText').textContent = 'Signing in…';
        document.getElementById('btnSpinner').style.display = 'block';
    });
