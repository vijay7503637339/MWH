</div>
<script>
const logoutLink = document.getElementById('logoutLink');
if (logoutLink) {
    logoutLink.addEventListener('click', async (event) => {
        event.preventDefault();
        try {
            await fetch('../api/auth/logout.php', {
                method: 'POST',
                credentials: 'same-origin'
            });
        } finally {
            window.location.href = 'login.php';
        }
    });
}

const categorySelect = document.getElementById('categorySelect');
const jobRoleSelect = document.getElementById('jobRoleSelect');

if (categorySelect && jobRoleSelect) {
    const selectedCategoryId = categorySelect.value;
    const selectedRoleId = jobRoleSelect.value;

    fetch('job_options.php', { credentials: 'same-origin' })
        .then(response => response.json())
        .then(data => {
            if (!data.success) throw new Error('Unable to load job options.');

            categorySelect.innerHTML = '<option value="">Select category</option>';
            data.categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.name;
                if (String(category.id) === String(selectedCategoryId)) option.selected = true;
                categorySelect.appendChild(option);
            });

            const renderRoles = (keepRoleId = '') => {
                jobRoleSelect.innerHTML = '<option value="">Select service / job role</option>';
                const categoryId = categorySelect.value;
                data.job_roles
                    .filter(role => String(role.category_id) === String(categoryId))
                    .forEach(role => {
                        const option = document.createElement('option');
                        option.value = role.id;
                        option.textContent = role.name;
                        if (String(role.id) === String(keepRoleId)) option.selected = true;
                        jobRoleSelect.appendChild(option);
                    });
            };

            renderRoles(selectedRoleId);
            categorySelect.addEventListener('change', () => renderRoles(''));
        })
        .catch(() => {
            jobRoleSelect.innerHTML = '<option value="">Unable to load job roles</option>';
        });
}
</script>
</body>
</html>
