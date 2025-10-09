<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Add Comments - Invoice Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #1a1a3e 0%, #ed1c24 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .report-date {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }

        .instructions {
            background: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 4px;
        }

        .instructions p {
            color: #333;
            line-height: 1.6;
        }

        .segment {
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }

        .segment-header {
            background: #f8f9fa;
            padding: 15px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }

        .segment-header:hover {
            background: #e9ecef;
        }

        .segment-header h2 {
            color: #333;
            font-size: 20px;
            font-weight: 600;
        }

        .segment-toggle {
            font-size: 24px;
            color: #667eea;
            font-weight: bold;
            transition: transform 0.3s;
        }

        .segment-toggle.collapsed {
            transform: rotate(-90deg);
        }

        .segment-content {
            padding: 20px;
            display: none;
        }

        .segment-content.expanded {
            display: block;
        }

        .segment-note {
            background: #fffbea;
            border-left: 3px solid #f59e0b;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #92400e;
        }

        .project {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .project:last-child {
            border-bottom: none;
        }

        .project-name {
            font-weight: 600;
            color: #333;
            font-size: 16px;
            margin-bottom: 15px;
        }

        .entry {
            margin-bottom: 15px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .entry-line {
            font-family: 'Courier New', monospace;
            color: #495057;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .comment-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 60px;
        }

        .comment-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .comment-label {
            display: block;
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }

        .button-container {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #e0e0e0;
        }

        .btn {
            flex: 1;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #f0f7ff;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="{{ route('invoice.index') }}" class="back-link">← Back to Upload</a>

        <h1>Add Optional Comments</h1>
        <p class="report-date">Invoice Report - {{ $reportDate }}</p>

        <div class="instructions">
            <p><strong>Optional:</strong> Add custom comments to any line items below. Comments will appear as bullet points under the aggregated hours. Leave fields blank if no comments are needed.</p>
        </div>

        <form action="{{ route('invoice.generate') }}" method="POST">
            @csrf

            @foreach($sections as $segmentName => $segment)
            <div class="segment">
                <div class="segment-header" onclick="toggleSegment(this)">
                    <h2>{{ $segmentName }}</h2>
                    <span class="segment-toggle">▼</span>
                </div>

                <div class="segment-content expanded">
                    @if(!empty($segment['note']))
                    <div class="segment-note">
                        {{ $segment['note'] }}
                    </div>
                    @endif

                    @foreach($segment['projects'] as $projectName => $project)
                    <div class="project">
                        <div class="project-name">{{ $projectName }}</div>

                        @php
                            $lines = $project['lines'];
                            $lineCount = count($lines);
                            $skipNext = false;
                        @endphp

                        @for($lineIndex = 0; $lineIndex < $lineCount; $lineIndex++)
                            @if($skipNext)
                                @php $skipNext = false; @endphp
                                @continue
                            @endif

                            @php
                                $line = $lines[$lineIndex];
                                $isExpense = strpos($line, '- $') === 0;
                                $nextLine = isset($lines[$lineIndex + 1]) ? $lines[$lineIndex + 1] : null;
                                $hasDescription = $nextLine && (strpos(ltrim($nextLine), '•') === 0 || strpos(ltrim($nextLine), chr(149)) === 0);
                            @endphp

                            <div class="entry">
                                @if($isExpense && $hasDescription)
                                    {{-- This is an expense with description - group them together --}}
                                    <div class="entry-line">{{ $line }}</div>
                                    <div class="entry-line">{{ $nextLine }}</div>

                                    @php
                                        // Use the FIRST line index for the entry ID
                                        $entryId = md5($segmentName . '_' . $projectName . '_' . $lineIndex);
                                        $skipNext = true;
                                    @endphp

                                    <label class="comment-label" for="comment_{{ $entryId }}">
                                        Add custom comment (optional):
                                    </label>
                                    <textarea
                                        name="comments[{{ $entryId }}]"
                                        id="comment_{{ $entryId }}"
                                        class="comment-input"
                                        placeholder="Add an additional comment for this expense..."
                                    ></textarea>
                                @else
                                    {{-- Regular labor entry --}}
                                    <div class="entry-line">{{ $line }}</div>

                                    @php
                                        $entryId = md5($segmentName . '_' . $projectName . '_' . $lineIndex);
                                    @endphp

                                    <label class="comment-label" for="comment_{{ $entryId }}">
                                        Add custom comment (optional):
                                    </label>
                                    <textarea
                                        name="comments[{{ $entryId }}]"
                                        id="comment_{{ $entryId }}"
                                        class="comment-input"
                                        placeholder="Enter a custom comment for this line item..."
                                    ></textarea>
                                @endif
                            </div>
                        @endfor
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach

            <div class="button-container">
                <button type="submit" name="skip" value="1" class="btn btn-secondary">
                    Skip - No Comments
                </button>
                <button type="submit" class="btn btn-primary">
                    Generate PDF with Comments
                </button>
            </div>
        </form>
    </div>

    <script>
        function toggleSegment(header) {
            const content = header.nextElementSibling;
            const toggle = header.querySelector('.segment-toggle');

            if (content.classList.contains('expanded')) {
                content.classList.remove('expanded');
                toggle.classList.add('collapsed');
            } else {
                content.classList.add('expanded');
                toggle.classList.remove('collapsed');
            }
        }

        // Start with first section expanded, rest collapsed
        document.addEventListener('DOMContentLoaded', function() {
            const segments = document.querySelectorAll('.segment');
            segments.forEach((segment, index) => {
                if (index > 0) {
                    const header = segment.querySelector('.segment-header');
                    toggleSegment(header);
                }
            });
        });
    </script>
</body>
</html>
