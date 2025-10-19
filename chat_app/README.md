# 카카오톡 스타일 웹 채팅 애플리케이션

PHP + MySQL을 이용한 실시간 웹 채팅 애플리케이션입니다. 카카오톡과 유사한 UI/UX를 제공하며, 1:1 채팅과 챗봇 기능을 지원합니다.

## 🚀 주요 기능

- **사용자 인증**: 로그인/회원가입 시스템
- **친구 목록**: 친구 관리 및 상태 표시
- **실시간 채팅**: Ajax를 이용한 2-3초 간격 메시지 갱신
- **챗봇**: 자동 응답 시스템 (인사말, 날씨, 시간, 기분 등)
- **카카오톡 스타일 UI**: 반응형 디자인과 직관적인 인터페이스

## 🛠️ 기술 스택

- **Backend**: PHP 8+
- **Database**: MySQL 8
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Icons**: Font Awesome 6
- **Server**: XAMPP (Apache + MySQL)

## 📁 프로젝트 구조

```
chat_app/
├── api/                    # API 엔드포인트
│   ├── getMessages.php     # 메시지 조회
│   ├── sendMessage.php     # 메시지 전송
│   ├── getFriends.php      # 친구 목록 조회
│   ├── createRoom.php      # 채팅방 생성
│   └── chatbot.php         # 챗봇 응답
├── assets/
│   └── css/
│       └── style.css       # 메인 스타일시트
├── database.sql            # 데이터베이스 스키마
├── db.php                  # 데이터베이스 연결
├── login.php               # 로그인 페이지
├── register.php            # 회원가입 페이지
├── chat.php                # 메인 채팅 페이지
├── logout.php              # 로그아웃 처리
└── README.md               # 프로젝트 문서
```

## 🚀 설치 및 실행

### 1. 환경 설정

1. **XAMPP 설치**
   - [XAMPP 다운로드](https://www.apachefriends.org/download.html)
   - Apache와 MySQL 서비스 시작

2. **프로젝트 배포**
   ```bash
   # XAMPP htdocs 폴더에 프로젝트 복사
   cp -r chat_app/ C:/xampp/htdocs/
   ```

### 2. 데이터베이스 설정

1. **MySQL 접속**
   - phpMyAdmin: `http://localhost/phpmyadmin`
   - 또는 MySQL 명령줄 도구 사용

2. **데이터베이스 생성**
   ```sql
   -- database.sql 파일 실행
   source C:/xampp/htdocs/chat_app/database.sql;
   ```

3. **데이터베이스 연결 확인**
   - `db.php` 파일에서 연결 정보 확인
   - 기본 설정: `localhost`, `root`, 비밀번호 없음

### 3. 애플리케이션 실행

1. **웹 브라우저에서 접속**
   ```
   http://localhost/chat_app/login.php
   ```

2. **테스트 계정으로 로그인**
   - **관리자**: `admin` / `password`
   - **사용자1**: `user1` / `password`
   - **사용자2**: `user2` / `password`

## 🎯 사용 방법

### 1. 회원가입 및 로그인
- 새 계정 생성 또는 기존 계정으로 로그인
- 로그인 후 자동으로 채팅 페이지로 이동

### 2. 친구와 채팅
- 좌측 친구 목록에서 친구 선택
- 우측 채팅창에서 메시지 입력 및 전송
- 실시간으로 메시지 갱신 (2-3초 간격)

### 3. 챗봇과 대화
- 친구 목록에서 "챗봇" 선택
- 다양한 키워드로 대화 시도:
  - **인사**: "안녕", "hello", "하이"
  - **날씨**: "날씨", "weather"
  - **시간**: "시간", "몇시"
  - **기분**: "기분", "좋아", "슬퍼"

## 🔧 주요 API 엔드포인트

### 메시지 관련
- `GET api/getMessages.php` - 메시지 조회
- `POST api/sendMessage.php` - 메시지 전송

### 친구 및 채팅방
- `GET api/getFriends.php` - 친구 목록 조회
- `POST api/createRoom.php` - 채팅방 생성

### 챗봇
- `POST api/chatbot.php` - 챗봇 응답 생성

## 🎨 UI/UX 특징

- **카카오톡 스타일 디자인**: 친숙한 인터페이스
- **반응형 레이아웃**: 모바일/데스크톱 지원
- **실시간 업데이트**: Ajax 기반 메시지 동기화
- **직관적 네비게이션**: 좌측 친구 목록, 우측 채팅창
- **상태 표시**: 온라인/오프라인 상태 표시

## 🔒 보안 기능

- **비밀번호 해싱**: `password_hash()` 사용
- **SQL 인젝션 방지**: PDO Prepared Statements
- **XSS 방지**: `htmlspecialchars()` 사용
- **세션 관리**: 안전한 사용자 인증

## 🐛 문제 해결

### 일반적인 문제들

1. **데이터베이스 연결 오류**
   - XAMPP MySQL 서비스가 실행 중인지 확인
   - `db.php`의 연결 정보 확인

2. **페이지가 로드되지 않음**
   - Apache 서비스가 실행 중인지 확인
   - 파일 경로가 올바른지 확인

3. **메시지가 실시간으로 업데이트되지 않음**
   - 브라우저 개발자 도구에서 네트워크 오류 확인
   - JavaScript 콘솔 오류 확인

## 📝 개발자 정보

- **개발 환경**: PHP 8+, MySQL 8, XAMPP
- **브라우저 지원**: Chrome, Firefox, Safari, Edge
- **반응형**: 모바일/태블릿/데스크톱 지원

## 🚀 향후 개선 계획

- [ ] 그룹 채팅 기능
- [ ] 파일/이미지 전송
- [ ] 메시지 암호화
- [ ] 푸시 알림
- [ ] 이모지 지원
- [ ] 메시지 검색 기능

---

**즐거운 채팅 되세요! 💬**
