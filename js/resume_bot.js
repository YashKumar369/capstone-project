document.addEventListener('DOMContentLoaded', function () {
    // 1. Create Widget HTML dynamically or assume it exists in footer
    // We'll manage the State here.

    const chatWindow = document.getElementById('resume-chat-window');
    const toggleBtn = document.getElementById('resume-chat-toggle');
    const form = document.getElementById('resume-form');
    const resultContainer = document.getElementById('resume-result');
    const previewContent = document.getElementById('resume-preview-content');
    const downloadBtn = document.getElementById('download-pdf-btn');
    const backBtn = document.getElementById('back-to-form-btn');
    const helperText = document.getElementById('resume-helper-text');

    if (!toggleBtn) return; // Widget not present

    // Toggle Window
    toggleBtn.addEventListener('click', function () {
        chatWindow.classList.toggle('d-none');
        // Simple animation logic if needed
    });

    // Handle Form Submit
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        // Show Loading
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
        submitBtn.disabled = true;

        // Gather Data
        const formData = {
            fullName: document.getElementById('resume-name').value,
            email: document.getElementById('resume-email').value,
            phone: document.getElementById('resume-phone').value,
            skills: document.getElementById('resume-skills').value,
            experience: document.getElementById('resume-experience').value,
            education: document.getElementById('resume-education').value,
            achievements: document.getElementById('resume-achievements').value
        };

        // Call API
        fetch('api/generate_resume.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                } else {
                    // Show Result
                    previewContent.innerHTML = data.html;

                    // Make Editable
                    previewContent.setAttribute('contenteditable', 'true');
                    previewContent.style.outline = 'none'; // Remove focus border for cleaner look

                    form.classList.add('d-none');
                    resultContainer.classList.remove('d-none');
                    helperText.innerHTML = "<i class='fas fa-pen'></i> <strong>Review & Edit:</strong> Click anywhere on the resume to make changes before downloading.";
                }
            })
            .catch(err => {
                alert('Request failed. Please try again.');
                console.error(err);
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
    });

    // Handle Download
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function () {
            const element = document.getElementById('resume-preview-content');
            const opt = {
                margin: [0.5, 0.5], // top/bottom, left/right
                filename: 'My_Resume.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, logging: true },
                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
            };

            // Wait for images to load (if any) before saving
            html2pdf().set(opt).from(element).save('My_Resume.pdf').catch(err => {
                console.error("PDF Generation Error:", err);
                alert("Failed to generate PDF. Please try again.");
            });
        });
    }

    // Back to Form
    if (backBtn) {
        backBtn.addEventListener('click', function () {
            resultContainer.classList.add('d-none');
            form.classList.remove('d-none');
            helperText.innerText = "Fill in your details to generate a professional resume.";
        });
    }
});
