document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('upload-form');
    var progressBar = document.getElementById('progress-bar');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var formData = new FormData(form);
        formData.append('action', 'godot_game_upload'); // Ensure this action matches the one registered in WordPress

        var xhr = new XMLHttpRequest();
        xhr.open('POST', godotAjax.ajaxurl, true);

        // Progress event
        xhr.upload.onprogress = function (event) {
            if (event.lengthComputable) {
                var percentComplete = event.loaded / event.total * 100;
                progressBar.value = percentComplete;
            }
        };

        // Load event
        xhr.onload = function () {
            if (xhr.status === 200) {
                alert('Upload successful!');
                console.log(xhr.responseText);
            } else {
                alert('An error occurred!');
            }
        };

        xhr.send(formData);
    });
});
