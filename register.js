document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("registerForm");
    const feedback = document.getElementById("feedback");
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(form);
        fetch('process_register.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
    if (data.result === "failed") {
        feedback.innerText = data.msg;
        feedback.className = "alert alert-danger";
        feedback.style.display = 'block';

        if (data.errorField) {
            const input = form.querySelector(`[name="${data.errorField}"]`);
            if (input) {
                input.focus();
                input.scrollIntoView({ behavior: "smooth", block: "center" });

                // clear field on focus
                input.addEventListener("focus", function clearOnce() {
                    input.value = "";
                    input.removeEventListener("focus", clearOnce);
                });
            }
        }
    } else if (data.result === "success") {
        feedback.innerText = data.msg;
        feedback.className = "alert alert-success";
        feedback.style.display = 'block';

        setTimeout(() => {
            window.location.href = "home.php";
        }, 2000);
    } else {
        console.warn("Unexpected result:", data);
    }
})

        .catch(err => {
            console.log(err);
        });
    });
});
document.querySelectorAll("#registerForm input").forEach(input => {
    input.addEventListener("focus", () => {
        input.value = "";
    });
});