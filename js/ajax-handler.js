document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('upload-form');
    var progressBar = document.getElementById('progress-bar');

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(form);
        var xhr = new XMLHttpRequest();

        // Update progress bar
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                var percentage = (e.loaded / e.total) * 100;
                progressBar.value = percentage;
            }
        };

        // Response handling
        xhr.onload = function() {
            if (xhr.status === 200) {
                console.log('Upload complete:', xhr.responseText);
            } else {
                console.error('Error:', xhr.statusText);
            }
        };

        // Send form data
        xhr.open('POST', form.action, true);
        xhr.send(formData);
    });
});
