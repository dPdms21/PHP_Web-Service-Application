# HAR 프로젝트 실행 안내서

본 문서는 HAR(Human Activity Recognition) 프로젝트를  
다른 PC(예: 교수님 노트북)에서 실행하기 위한 필수 내용을 정리한 안내서입니다.

---

## 1. 프로젝트 폴더 구성

아래 항목이 모두 포함되면 프로젝트를 문제 없이 실행할 수 있습니다.

```
human_activity_recognition/
│   requirements.txt
│
├── py/
├── public/
├── temp_infer/
├── outputs/
└── data/        (필요 시)
```

### 필수 포함 폴더/파일
- py/
- public/
- outputs/
- temp_infer/
- requirements.txt

---

## 2. Python 실행 환경 설정

### 2.1 가상환경 생성
```
python -m venv venv
```

### 2.2 가상환경 활성화
```
Set-ExecutionPolicy -Scope Process -ExecutionPolicy RemoteSigned
venv\Scripts\activate
```

### 2.3 필요 라이브러리 설치
```
pip install -r requirements.txt
```

---

## 3. 실행해야 하는 Python 파일 (추론)

훈련(train) 파일은 실행할 필요가 없습니다.  
이미 outputs/ 폴더에 학습된 모델이 포함되어 있으며,  
추론만 수행하면 됩니다.

### Fusion HAR 영상 추론 실행
```
python py/fusion/infer_fusion_video.py
```

---

## 4. 훈련용 파일

아래 파일들은 학습(train)을 다시 수행하는 코드입니다.

```
py/image/train_image_resnet_fast_v2.py
py/audio/train_audio_resnet_fast.py
py/fusion/train_fusion_resnet_fast.py
py/fusion/infer_fusion_video.py
```

---

## 5. 웹 대시보드 실행 (PHP)

웹 브라우저를 통해 다음 주소를 열면 됩니다.

```
http://localhost/webS/human_activity_recognition/public/image2.php
http://localhost/webS/human_activity_recognition/public/audio.php
http://localhost/webS/human_activity_recognition/public/fusion.php
http://localhost/webS/human_activity_recognition/public/fusion_result.php
```

---

## 6. outputs 폴더의 역할

추론 및 웹 대시보드 표시를 위한 모델(.pth), 성능 파일(metrics.json),  
confusion_matrix 이미지 등이 포함되어 있습니다.

outputs 폴더가 없으면 추론 및 대시보드 기능이 정상 작동하지 않습니다.
