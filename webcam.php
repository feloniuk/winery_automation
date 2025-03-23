<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Веб-камера</title>
    <style>
        body { 
            margin: 0; 
            padding: 0; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            background: #000; 
            overflow: hidden;
        }
        video { 
            width: 100%; 
            height: 100vh; 
            object-fit: cover;
        }
    </style>
</head>
<body>
    <video id="video" autoplay playsinline></video>
    
    <script>
        const video = document.getElementById('video');
        
        async function startVideo() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                });
                
                video.srcObject = stream;
            } catch (err) {
                console.error("Ошибка доступа к камере:", err);
                document.body.innerHTML = "<p style='color: white; text-align: center;'>Ошибка доступа к камере. Убедитесь, что камера подключена и разрешения выданы.</p>";
            }
        }
        
        startVideo();
    </script>
</body>
</html>