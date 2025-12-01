document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("bm-booking-form");
    const message = document.getElementById("bm-message");

    if (!form) return;

    form.addEventListener("submit", async function (e) {
        e.preventDefault();

        const formData = new FormData(form);

        const response = await fetch(bm_ajax.ajax_url, {
            method: "POST",
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            message.textContent = "Reservation successful!";
            message.className = "bm-success";
            form.reset();
        } else {
            message.textContent = result.message || "Something went wrong.";
            message.className = "bm-error";
        }
    });
});