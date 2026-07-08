/* ── Password visibility toggle ── */
    function togglePw(fieldId, btnId) {
        const field = document.getElementById(fieldId);
        const isText = field.type === 'text';
        field.type = isText ? 'password' : 'text';
        document.getElementById(btnId).style.color = isText ? '' : 'var(--purple-main)';
    }

    /* ── Password strength meter ── */
    function checkStrength(val) {
        let score = 0;
        if (val.length >= 8)                    score++;
        if (/[A-Z]/.test(val))                  score++;
        if (/[0-9]/.test(val))                  score++;
        if (/[^A-Za-z0-9]/.test(val))           score++;

        const colors = ['', '#ef4444', '#f59e0b', '#3b82f6', '#10b981'];
        const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];

        ['bar1','bar2','bar3','bar4'].forEach((id, i) => {
            document.getElementById(id).style.background =
                i < score ? colors[score] : 'var(--gray-200)';
        });
        document.getElementById('pwLabel').textContent = val.length ? labels[score] : '';
        document.getElementById('pwLabel').style.color = colors[score];
    }

    /* ── Drag-and-drop highlight ── */
    const zone = document.getElementById('uploadZone');
    zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop',      e => { e.preventDefault(); zone.classList.remove('drag-over'); });

    /* ── Image preview ── */
    function previewImage(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('previewName').textContent = file.name;
            document.getElementById('uploadPreview').style.display = 'flex';
            zone.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }

    function removeImage() {
        document.getElementById('profile_image').value = '';
        document.getElementById('uploadPreview').style.display = 'none';
        zone.style.display = 'block';
    }

    /* ── Submit spinner ── */
    document.getElementById('registerForm').addEventListener('submit', function () {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        document.getElementById('btnText').textContent = 'Creating account…';
        document.getElementById('btnSpinner').style.display = 'block';
    });
