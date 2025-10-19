<?php
$csvFile = __DIR__ . '/../output/topic_by_time_simple.csv';

$data = [];
if (($handle = fopen($csvFile, "r")) !== false) {
    fgetcsv($handle);
    
    while (($row = fgetcsv($handle)) !== false) {
        $row = array_map('trim', $row);
        if (count($row) >= 3) {
            $data[] = [
                "time_range"   => $row[0],
                "single_topic" => $row[1],
                "count"        => $row[2]
            ];
        }
    }
    fclose($handle);
}

// 시간대와 토픽별 데이터 가공
$timeRanges = ["새벽", "오전", "오후", "저녁"];
$topics = array_unique(array_column($data, "single_topic"));
$topics = array_values($topics); // 인덱스 리셋

// PHP → JS 전달용 구조
$chartData = [];
foreach ($topics as $topic) {
    $counts = [];
    foreach ($timeRanges as $t) {
        $found = array_filter($data, fn($d) => $d["time_range"] === $t && $d["single_topic"] === $topic);
        $counts[] = $found ? intval(array_values($found)[0]["count"]) : 0;
    }
    $chartData[] = [
        "label" => $topic,
        "data" => $counts
    ];
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>시간대별 토픽 분포</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            display: flex;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .left {
            width: 30%;
            padding: 20px;
            background: #f9f9f9;
        }
        .right {
            width: 70%;
            padding: 20px;
        }
        h1 {
            font-size: 20px;
            margin-bottom: 10px;
        }
        canvas {
            width: 100% !important;
            height: 500px !important;
        }
    </style>
</head>
<body>
    <div class="left">
        <h1>데이터셋 설명</h1>
        <p><b>출처:</b> AI Hub 「SNS 데이터 고도화」</p>
        <p><b>데이터 범위:</b><br>
           원본 데이터셋 중 <code>22_05_01_03_fcc8a83e...</code> ~ 
           <code>22_05_01_04_ffdd2540...</code>까지 총 1,000개의 JSON 대화 샘플 사용</p>
        <p><b>전처리 과정:</b><br>
           JSON 데이터를 발화 단위로 분리 후, 
           <code>time_range</code>(시간대), <code>single_topic</code>(단일 주제), <code>count</code>(발화 수) 컬럼으로 CSV 변환.<br>
           불필요한 메타데이터는 제거하고 주요 텍스트 라벨만 활용</p>
        <p><b>시간대 분류 기준:</b><br>
           새벽(00:00~06:00), 오전(06:00~12:00), 오후(12:00~18:00), 저녁(18:00~24:00)<br>
           → 일상 생활 패턴(수면·업무·여가)에 맞춰 4구간으로 설정</p>
        <p><b>사용 라벨:</b><br>
           개인 및 관계, 행사, 여가 생활, 시사/교육, 일과 직업, 주거와 생활, 
           미용과 건강, 상거래(쇼핑), 식음료</p>
        <p><b>분석 결과:</b><br>
           - <b>개인 및 관계</b>: 전체 발화 중 가장 높은 비중을 차지하며, 오후·저녁에 집중적으로 증가 → <i>퇴근 이후 사적 대화 증가와 연관</i><br>
           - <b>시사/교육</b>: 새벽 시간대에 상대적으로 높음 → <i>심야 뉴스 소비·학습 활동과 유사한 패턴</i><br>
           - <b>행사, 여가 생활, 미용과 건강</b>: 오전 이후 점차 증가하여 오후에 최고치 → <i>일상 활동 증가와 함께 정보 교류 확대</i><br>
           - <b>식음료</b>: 전체 발화량은 낮지만 오후~저녁 시간대에 꾸준히 증가 → <i>식사·소비 활동과 일치</i></p>
        <p><b>시각화 목적:</b><br>
           시간대별 주제 집중도를 시각화하여, 
           사용자의 <b>활동 패턴과 토픽별 특징을 비교·분석</b>하는 근거 자료로 활용 가능</p>
    </div>
    <div class="right">
        <h1>시간대별 토픽 분포</h1>
        <canvas id="topicChart"></canvas>
    </div>

    <script>
        const ctx = document.getElementById('topicChart').getContext('2d');
        const chartData = <?php echo json_encode($chartData, JSON_UNESCAPED_UNICODE); ?>;
        const timeRanges = <?php echo json_encode($timeRanges, JSON_UNESCAPED_UNICODE); ?>;

        const colors = [
            "#4e79a7", "#f28e2b", "#e15759", "#76b7b2",
            "#59a14f", "#edc948", "#b07aa1", "#ff9da7",
            "#9c755f", "#bab0ac"
        ];

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: timeRanges,
                datasets: chartData.map((d, i) => ({
                    label: d.label,
                    data: d.data,
                    backgroundColor: colors[i % colors.length],
                    stack: "stack1"
                }))
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: "bottom" }
                },
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>
