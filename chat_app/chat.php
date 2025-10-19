<?php
require_once 'db.php';

// 로그인 확인
requireLogin();

$current_user = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>채팅 - <?php echo htmlspecialchars($current_user['nickname']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=3">
    <!-- <link rel="stylesheet" href="assets/css/style.css?v=1"> -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="chat-container">
        <!-- 사이드바 -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="user-info">
                    <?php if ($current_user['profile_image'] && $current_user['profile_image'] !== 'default_profile.png' && $current_user['profile_image'] !== 'default.png' && file_exists('assets/uploads/' . $current_user['profile_image'])): ?>
                        <img src="assets/uploads/<?php echo htmlspecialchars($current_user['profile_image']); ?>" 
                             alt="프로필 이미지" class="user-avatar" style="object-fit: cover;">
                    <?php else: ?>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($current_user['nickname'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($current_user['nickname']); ?></div>
                        <small style="opacity: 0.8;">온라인</small>
                    </div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="settings-btn" onclick="openProfile()" title="프로필 설정">
                        <i class="fas fa-cog"></i>
                    </button>
                    <button class="logout-btn" onclick="logout()" title="로그아웃">
                        <i class="fas fa-sign-out-alt"></i>
                    </button>
                </div>
            </div>
            
            <!-- 친구 검색 및 추가 -->
            <div class="search-section">
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="친구 검색..." class="search-input">
                    <button onclick="searchFriends()" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <button onclick="showAddFriendModal()" class="add-friend-btn">
                    <i class="fas fa-user-plus"></i> 친구 추가
                </button>
            </div>
            
            <!-- 친구 목록 -->
            <div class="friends-list" id="friendsList">
                <div class="loading-message">
                    <i class="fas fa-spinner fa-spin"></i> 친구 목록을 불러오는 중...
                </div>
            </div>
            
            <!-- 채팅방 생성 버튼 -->
            <button class="create-room-btn" onclick="showCreateRoomModal()" title="새 채팅방 만들기">
                <i class="fas fa-plus"></i>
            </button>
        </div>
        
        <!-- 메인 채팅 영역 -->
        <div class="chat-main">
            <div class="chat-header" id="chatHeader" style="display: none;">
                <div class="chat-title" id="chatTitle">채팅방을 선택해주세요</div>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <div class="welcome-message">
                    <i class="fas fa-comments"></i>
                    <h3>채팅을 시작해보세요!</h3>
                    <p>좌측에서 친구를 선택하여 대화를 시작할 수 있습니다.</p>
                </div>
            </div>
            
            <div class="chat-input" id="chatInput" style="display: none;">
                <textarea 
                    class="message-input" 
                    id="messageInput" 
                    placeholder="메시지를 입력하세요..."
                    rows="1"
                ></textarea>
                <button class="send-btn" id="sendBtn" onclick="sendMessage()">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
        
    </div>
    
    <!-- 친구 추가 모달 -->
    <div id="addFriendModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>친구 추가</h3>
                <span class="close" onclick="closeAddFriendModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="search-container">
                    <input type="text" id="modalSearchInput" placeholder="닉네임 또는 이메일로 검색..." class="search-input">
                    <button onclick="searchUsers()" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <div id="searchResults" class="search-results"></div>
            </div>
        </div>
    </div>
    
    <!-- 채팅방 생성 모달 -->
    <div id="createRoomModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>새 채팅방 만들기</h3>
                <span class="close" onclick="closeCreateRoomModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="room-type-selector">
                    <label>
                        <input type="radio" name="roomType" value="private" checked onchange="toggleRoomType()">
                        1:1 채팅
                    </label>
                    <label>
                        <input type="radio" name="roomType" value="group" onchange="toggleRoomType()">
                        그룹 채팅
                    </label>
                </div>
                <div id="groupRoomName" class="group-room-name" style="display: none;">
                    <input type="text" id="roomNameInput" placeholder="그룹 채팅방 이름을 입력하세요..." class="form-control">
                </div>
                <div class="friends-selection">
                    <h4>친구 선택</h4>
                    <div id="friendsCheckboxList" class="friends-checkbox-list"></div>
                </div>
                <div class="modal-footer">
                    <button onclick="createRoom()" class="btn btn-primary">채팅방 만들기</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    let currentRoomId = null;
    let renderedMessageIds = new Set();
    let lastMessageId = 0;
    let messageInterval = null;
    let friendsData = [];
    let roomsData = [];
    let selectedFriends = [];
    let isChatbotRoom = false;

    const CURRENT_USER_NICKNAME = "<?php echo htmlspecialchars($current_user['nickname'], ENT_QUOTES); ?>";

    document.addEventListener('DOMContentLoaded', function() {
        loadFriends();
        setupEventListeners();
    });

    function handleEnterPress(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    }

    function setupEventListeners() {
        const messageInput = document.getElementById('messageInput');
        messageInput.removeEventListener('keypress', handleEnterPress);
        messageInput.addEventListener('keypress', handleEnterPress);
        messageInput.removeEventListener('input', autoResize);
        messageInput.addEventListener('input', autoResize);

        const searchInput = document.getElementById('searchInput');
        const modalSearchInput = document.getElementById('modalSearchInput');
        searchInput.onkeypress = (e) => { if (e.key === 'Enter') searchFriends(); };
        modalSearchInput.onkeypress = (e) => { if (e.key === 'Enter') searchUsers(); };
    }

    function autoResize() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    }

    // 친구 목록 + 내가 포함된 채팅방 불러오기
    function loadFriends() {
        fetch('api/getFriends.php')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    friendsData = Array.isArray(data.friends) ? data.friends : [];
                    roomsData = Array.isArray(data.rooms) ? data.rooms : [];
                    renderFriendsList();
                } else {
                    console.error(data.error || '친구 목록 불러오기 실패');
                    showError('친구 목록을 불러올 수 없습니다.');
                }
            })
            .catch(err => {
                console.error(err);
                showError('친구 목록을 불러오는 중 오류가 발생했습니다.');
            });
    }

    // 목록 렌더링
    function renderFriendsList() {
        const friendsList = document.getElementById('friendsList');

        if ((!friendsData || friendsData.length === 0) && (!roomsData || roomsData.length === 0)) {
            friendsList.innerHTML = `
                <div class="empty-message">
                    <i class="fas fa-user-friends"></i>
                    <p>친구 또는 채팅방이 없습니다.</p>
                </div>`;
            return;
        }

        const combined = [];

        // 친구 항목
        friendsData.forEach(f => {
            combined.push({
                type: 'friend',
                id: f.id,
                name: f.nickname,
                username: f.username,
                room_id: f.room_id || null,
                last_message: f.last_message || '',
                formatted_time: f.formatted_time || ''
            });
        });

        // 내가 포함된 채팅방
        roomsData.forEach(r => {
            combined.push({
                type: 'room',
                id: r.room_id,
                name: r.display_name || r.room_name || '채팅방',
                room_id: r.room_id,
                last_message: r.last_message || '',
                formatted_time: r.last_message_time || '',
                room_type: r.room_type || 'private',   // room_type 추가
                unread_count: Number(r.unread_count) || 0
            });
        });

        // 중복 제거
        const seen = new Set();
        const unique = combined.filter(item => {
            const key = `${item.type}-${item.id}`;
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });

        friendsList.innerHTML = unique.map(friend => {
            const preview = (friend.last_message || '').trim().slice(0, 20);
            const avatar = friend.name.charAt(0).toUpperCase();
            const displayTime = formatDate(friend.formatted_time);
            const unreadCount = friend.unread_count && friend.unread_count > 0
            ? `<span class="unread-count">${friend.unread_count > 99 ? '99+' : friend.unread_count}</span>`
            : '';

            // 단체 채팅방만 아이콘 표시
            const isGroupRoom = friend.type === 'room' && friend.room_type === 'group';
            const icon = isGroupRoom ? ' <i class="fas fa-users" style="color:#888;"></i>' : '';

            return `
                <div class="friend-item" 
                    onclick="selectFriend(${friend.id}, '${friend.name}', ${friend.room_id || 'null'})">
                    <div class="friend-avatar">${avatar}</div>
                    <div class="friend-info">
                        <div class="friend-name">${friend.name}${icon}</div>
                        <div class="friend-last-message">${preview || ''}</div>
                    </div>
                    <div class="friend-time">${unreadCount}<span class="time-text">${displayTime}</span></div>
                </div>`;
        }).join('');
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(d)) return '';

        const now = new Date();
        const isToday =
            d.getFullYear() === now.getFullYear() &&
            d.getMonth() === now.getMonth() &&
            d.getDate() === now.getDate();

        const hh = String(d.getHours()).padStart(2, '0');
        const mi = String(d.getMinutes()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');

        // 오늘이면 "HH:MM", 오늘 아니면 "MM-DD"
        return isToday ? `${hh}:${mi}` : `${mm}-${dd}`;
    }

    // 친구 또는 방 클릭 시
    function selectFriend(friendId, friendName, roomId) {
        document.querySelectorAll('.friend-item').forEach(i => i.classList.remove('active'));
        event.currentTarget.classList.add('active');

        document.getElementById('chatHeader').style.display = 'flex';
        document.getElementById('chatTitle').textContent = friendName;
        document.getElementById('chatInput').style.display = 'flex';

        // 메시지 영역 초기화
        const chatMessages = document.getElementById('chatMessages');
        chatMessages.innerHTML = '';
        renderedMessageIds.clear();
        lastMessageId = 0;

        // 챗봇 여부 판단
        isChatbotRoom = !!friendsData.find(f => f.id === friendId && f.username === 'chatbot');

        if (messageInterval) {
            clearInterval(messageInterval);
            messageInterval = null;
        }

        if (roomId) loadChatRoom(roomId);
        else createChatRoom(friendId);
    }

    // 방 로드
    function loadChatRoom(roomId) {
        if (!roomId) return;

        currentRoomId = roomId;
        lastMessageId = 0;
        renderedMessageIds.clear();

        const chatMessages = document.getElementById('chatMessages');
        chatMessages.innerHTML = '';

        if (messageInterval) clearInterval(messageInterval);

        loadMessages();
        markRoomAsRead(roomId);

        messageInterval = setInterval(() => {
            if (!document.hidden && currentRoomId === roomId) {
                loadMessages();
            }
        }, 2000);
    }

    // 메시지 불러오기
    function loadMessages() {
        if (!currentRoomId) return;

        fetch(`api/getMessages.php?room_id=${currentRoomId}&last_message_id=${lastMessageId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.messages.length > 0) {
                    isChatbotRoom = !!data.is_chatbot_room;
                    renderMessages(data.messages);
                    lastMessageId = Math.max(...data.messages.map(m => m.id));
                    scrollToBottom();
                }
            })
            .catch(() => {});
    }

    // 메시지 렌더링
    function renderMessages(messages) {
        const chatMessages = document.getElementById('chatMessages');

        messages.forEach(message => {
            if (renderedMessageIds.has(message.id)) return;
            renderedMessageIds.add(message.id);

            const div = document.createElement('div');
            div.className = `message ${message.is_sent ? 'sent' : 'received'}`;
            div.dataset.id = message.id;

            if (message.sender_username === 'chatbot') div.classList.add('bot');

            // 읽음 상태 — 챗봇은 표시 안함
            let readStatus = '';
            if (!isChatbotRoom && message.is_sent && message.read_count !== undefined) {
                readStatus = message.read_count === 0
                    ? '<div class="read-status read">읽음</div>'
                    : `<div class="read-status unread">${message.read_count}</div>`;
            }

            div.innerHTML = `
                <div class="message-bubble">${message.content}</div>
                <div class="message-time">${message.formatted_time}${readStatus}</div>
            `;
            chatMessages.appendChild(div);
        });
    }

    // 메시지 전송
    function sendMessage() {
        const messageInput = document.getElementById('messageInput');
        const content = messageInput.value.trim();
        if (!content || !currentRoomId) return;

        const fd = new FormData();
        fd.append('room_id', currentRoomId);
        fd.append('content', content);
        fd.append('message_type', 'text');

        const sendBtn = document.getElementById('sendBtn');
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        fetch('api/sendMessage.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                    loadFriends();
                    checkAndRespondWithChatbot(content);
                } else {
                    showError('메시지 전송에 실패했습니다.');
                }
            })
            .catch(() => showError('메시지 전송 중 오류가 발생했습니다.'))
            .finally(() => {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
            });
    }

    // 챗봇 응답
    function checkAndRespondWithChatbot(message) {
        if (!isChatbotRoom) return;
        const fd = new FormData();
        fd.append('message', message);
        fetch('api/chatbot.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => { if (data.success) setTimeout(() => loadMessages(), 1000); })
            .catch(() => {});
    }

    // 읽음 표시 갱신
    function markRoomAsRead(roomId) {
        const fd = new FormData();
        fd.append('room_id', roomId);
        fetch('api/markRoomRead.php', { method: 'POST', body: fd })
            .then(() => loadFriends())
            .catch(() => {});
    }

    function scrollToBottom() {
        const chatMessages = document.getElementById('chatMessages');
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    //채팅방 생성 모달 열기
    function showCreateRoomModal() {
    const modal = document.getElementById('createRoomModal');
    modal.style.display = 'block';
    selectedFriends = [];

    // 모달은 전체 친구 목록 불러오기
    fetch('api/getAllFriends.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                friendsData = data.friends || [];
                loadFriendsForRoom();
            } else {
                alert('친구 목록을 불러올 수 없습니다.');
            }
        })
        .catch(() => alert('친구 목록을 불러오는 중 오류가 발생했습니다.'));
    }

    // 모달 닫기
    function closeCreateRoomModal() {
        const modal = document.getElementById('createRoomModal');
        modal.style.display = 'none';

        selectedFriends = [];
        document.getElementById('roomNameInput').value = '';
    }

    // 1:1/그룹 전환 시 입력창 표시
    function toggleRoomType() {
        const roomType = document.querySelector('input[name="roomType"]:checked').value;
        const groupName = document.getElementById('groupRoomName');
        groupName.style.display = (roomType === 'group') ? 'block' : 'none';
        loadFriendsForRoom();
    }

    // 친구 목록 불러오기 (모달 내부용)
    function loadFriendsForRoom() {
        const list = document.getElementById('friendsCheckboxList');
        const roomType = document.querySelector('input[name="roomType"]:checked').value;
        const inputType = (roomType === 'private') ? 'radio' : 'checkbox';

        if (!friendsData || friendsData.length === 0) {
            list.innerHTML = `<p class="no-friends">친구가 없습니다.</p>`;
            return;
        }

        list.innerHTML = friendsData.map(friend => {
            const hasImg = friend.profile_image &&
                        friend.profile_image !== 'default_profile.png' &&
                        friend.profile_image !== 'default.png';
            const avatar = hasImg
                ? `<img src="assets/uploads/${friend.profile_image}" alt="${friend.nickname}">`
                : `<div class="friend-avatar">${friend.nickname.charAt(0).toUpperCase()}</div>`;

            return `
                <label class="friend-checkbox-item">
                    <input type="${inputType}" 
                        name="roomFriend" 
                        value="${friend.id}"
                        onchange="toggleFriendSelection(${friend.id}, this)">
                    ${avatar}
                    <span class="friend-name">${friend.nickname}</span>
                </label>
            `;
        }).join('');
    }

    // 친구 선택 (1:1은 하나만)
    function toggleFriendSelection(friendId, el) {
        const roomType = document.querySelector('input[name="roomType"]:checked').value;

        if (roomType === 'private') {
            selectedFriends = [friendId];
            document.querySelectorAll('#friendsCheckboxList input[name="roomFriend"]').forEach(i => {
                i.checked = (i.value === String(friendId));
            });
        } else {
            if (el.checked) {
                if (!selectedFriends.includes(friendId)) selectedFriends.push(friendId);
            } else {
                selectedFriends = selectedFriends.filter(id => id !== friendId);
            }
        }
    }

    // 채팅방 생성
    function createRoom() {
        const roomType = document.querySelector('input[name="roomType"]:checked').value;
        const roomName = document.getElementById('roomNameInput').value.trim();

        if (selectedFriends.length === 0) return alert('친구를 선택해주세요.');
        if (roomType === 'private' && selectedFriends.length > 1)
            return alert('1:1 채팅은 한 명만 선택할 수 있습니다.');
        if (roomType === 'group' && !roomName)
            return alert('그룹 채팅방 이름을 입력해주세요.');

        const fd = new FormData();
        fd.append('room_type', roomType);
        fd.append('friend_ids', JSON.stringify(selectedFriends));
        if (roomType === 'group') fd.append('room_name', roomName);

        fetch('api/createRoom.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return alert(data.error || '채팅방 생성 실패');
                closeCreateRoomModal();
                loadFriends();

                // 생성 직후 방 바로 열기
                if (data.room_id) {
                    document.getElementById('chatHeader').style.display = 'flex';
                    document.getElementById('chatTitle').textContent =
                        (roomType === 'group' ? roomName : '새 채팅');
                    document.getElementById('chatInput').style.display = 'flex';
                    loadChatRoom(data.room_id);
                }
            })
            .catch(() => alert('채팅방 생성 중 오류가 발생했습니다.'));
    }

    // 바깥 클릭 시 모달 닫기
    window.onclick = function(event) {
        const addFriendModal = document.getElementById('addFriendModal');
        const createRoomModal = document.getElementById('createRoomModal');
        if (event.target === addFriendModal) closeAddFriendModal();
        if (event.target === createRoomModal) closeCreateRoomModal();
    };

    function searchFriends() {
        const q = document.getElementById('searchInput').value.trim().toLowerCase();
        const friendsList = document.getElementById('friendsList');

        // 아무 것도 입력 안 하면 전체 목록 다시 로드
        if (!q) return renderFriendsList();

        // friendsData + roomsData 합쳐서 검색
        const filtered = [];

        friendsData.forEach(f => {
            const name = (f.nickname || '').toLowerCase();
            const username = (f.username || '').toLowerCase();
            if (name.includes(q) || username.includes(q)) {
                filtered.push({
                    type: 'friend',
                    id: f.id,
                    name: f.nickname,
                    username: f.username,
                    room_id: f.room_id || null,
                    last_message: f.last_message || '',
                    formatted_time: f.formatted_time || ''
                });
            }
        });

        roomsData.forEach(r => {
            const displayName = (r.display_name || '').toLowerCase();
            const participants = (r.participant_names || '').toLowerCase();
            const combined = `${displayName} ${participants}`; // 방 이름 + 참여자 모두 검색대상

            if (combined.includes(q)) {
                filtered.push({
                    type: 'room',
                    id: r.room_id,
                    name: r.display_name || r.room_name || '채팅방',
                    room_id: r.room_id,
                    last_message: r.last_message || '',
                    formatted_time: r.last_message_time || '',
                    room_type: r.room_type || 'private',
                    unread_count: Number(r.unread_count) || 0
                });
            }
        });

        if (filtered.length === 0) {
            friendsList.innerHTML = `
                <div class="empty-message">
                    <i class="fas fa-search"></i>
                    <p>검색 결과가 없습니다.</p>
                </div>`;
            return;
        }

        friendsList.innerHTML = filtered.map(item => {
            const preview = (item.last_message || '').trim().slice(0, 20);
            const avatar = item.name.charAt(0).toUpperCase();
            const displayTime = formatDate(item.formatted_time);
            const unreadCount = item.unread_count && item.unread_count > 0
                ? `<span class="unread-count">${item.unread_count > 99 ? '99+' : item.unread_count}</span>`
                : '';
            const isGroup = item.type === 'room' && item.room_type === 'group';
            const icon = isGroup ? ' <i class="fas fa-users" style="color:#888;"></i>' : '';

            return `
                <div class="friend-item" onclick="selectFriend(${item.id}, '${item.name}', ${item.room_id || 'null'})">
                    <div class="friend-avatar">${avatar}</div>
                    <div class="friend-info">
                        <div class="friend-name">${item.name}${icon}</div>
                        <div class="friend-last-message">${preview || ''}</div>
                    </div>
                    <div class="friend-time">${unreadCount}<span class="time-text">${displayTime}</span></div>
                </div>`;
        }).join('');
    }

    // =========================
    // 친구 추가 기능 관련 함수들
    // =========================

    // 친구 추가 모달 열기
    function showAddFriendModal() {
        const modal = document.getElementById('addFriendModal');
        modal.style.display = 'block';
        document.getElementById('modalSearchInput').focus();
    }

    // 친구 추가 모달 닫기
    function closeAddFriendModal() {
        const modal = document.getElementById('addFriendModal');
        modal.style.display = 'none';
        document.getElementById('searchResults').innerHTML = '';
        document.getElementById('modalSearchInput').value = '';
    }

    // 친구 검색 (모달 내부)
    function searchUsers() {
        const q = document.getElementById('modalSearchInput').value.trim();
        const results = document.getElementById('searchResults');

        if (!q) {
            results.innerHTML = '<p class="no-results">검색어를 입력해주세요.</p>';
            return;
        }

        fetch(`api/searchFriend.php?q=${encodeURIComponent(q)}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    results.innerHTML = '<p class="no-results">검색 중 오류가 발생했습니다.</p>';
                    return;
                }
                if (!data.users || data.users.length === 0) {
                    results.innerHTML = '<p class="no-results">검색 결과가 없습니다.</p>';
                    return;
                }
                renderUserSearchResults(data.users);
            })
            .catch(() => {
                results.innerHTML = '<p class="no-results">검색 중 오류가 발생했습니다.</p>';
            });
    }

    // 검색 결과 렌더링
    function renderUserSearchResults(users) {
        const el = document.getElementById('searchResults');

        el.innerHTML = users.map(user => {
            const avatar = user.profile_image && user.profile_image !== 'default.png' && user.profile_image !== 'default_profile.png'
                ? `<img src="assets/uploads/${user.profile_image}" alt="${user.nickname}" class="user-avatar-small">`
                : `<div class="user-avatar-small">${user.nickname.charAt(0).toUpperCase()}</div>`;

            let actionHTML = '';
            if (user.friendship_status === 'accepted') {
                actionHTML = `<span class="friend-status accepted">친구</span>`;
            } else if (user.friendship_status === 'pending') {
                actionHTML = `<span class="friend-status pending">요청 중</span>`;
            } else {
                actionHTML = `<button onclick="addFriend(${user.id})" class="add-friend-btn-small">추가</button>`;
            }

            return `
                <div class="user-item">
                    ${avatar}
                    <div class="user-info">
                        <div class="user-name">${user.nickname}</div>
                        <div class="user-email">${user.email}</div>
                    </div>
                    <div class="user-actions">${actionHTML}</div>
                </div>
            `;
        }).join('');
    }

    // 친구 추가 요청
    function addFriend(friendId) {
        const fd = new FormData();
        fd.append('friend_id', friendId);

        fetch('api/addFriend.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('친구 요청이 전송되었습니다.');
                    searchUsers(); // 다시 목록 갱신
                    loadFriends(); // 친구 목록 새로고침
                } else {
                    alert(data.error || '친구 추가 중 오류가 발생했습니다.');
                }
            })
            .catch(() => {
                alert('친구 추가 요청 중 오류가 발생했습니다.');
            });
    }

    function showError(message) { alert(message); }
    function openProfile() { window.location.href = 'profile.php'; }
    function logout() { window.location.href = 'logout.php'; }

    window.addEventListener('beforeunload', () => { if (messageInterval) clearInterval(messageInterval); });
    </script>
</body>
</html>