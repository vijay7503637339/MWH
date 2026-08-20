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
</script>
</body>
</html>
