<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class AuthorizedTurtleSoupSeeder extends AbstractSeed
{
    private const LANGUAGE = 'zh-CN';

    private const SOURCE_TYPE = 'authorized_seed';

    private const LTDA_SOURCE_TYPE = 'ltda_cc_by_sa';

    private const LTDA_LICENSE = 'CC BY-SA 3.0';

    private const EXISTING_QUESTION_TAGS = [
        '7AFHK2XKV0H387AAEPR9NY6QPT' => [
            'classic', 'mystery', 'logic', 'honkaku', 'identity-misdirection', 'accident', 'dark', 'black-soup',
        ],
        'R2X2C4RMHMWXWME9WNT6KQY1H7' => [
            'mystery', 'logic', 'neo-honkaku', 'family', 'emotional', 'medical', 'identity-misdirection', 'red-soup',
        ],
    ];

    private const REQUIRED_TAGS = [
        'classic', 'daily-life', 'mystery', 'logic', 'crime', 'accident', 'workplace',
        'campus', 'family', 'emotional', 'medical', 'historical', 'supernatural', 'dark',
        'humorous', 'short', 'honkaku', 'henkaku', 'neo-honkaku', 'wordplay',
        'identity-misdirection', 'unreliable-narrator', 'time-trick', 'closed-room',
        'multiple-reversal', 'one-line', 'clear-soup', 'red-soup', 'black-soup',
    ];

    public function run(): void
    {
        $source = __DIR__ . '/data/authorized_turtle_soup_2026.md';
        $markdown = file_get_contents($source);
        if ($markdown === false) {
            throw new RuntimeException('Authorized turtle soup source file cannot be read.');
        }

        $authorizedStories = $this->parse($markdown);
        if (count($authorizedStories) !== 63) {
            throw new RuntimeException(sprintf('Expected 63 authorized stories, parsed %d.', count($authorizedStories)));
        }

        $ltdaStories = $this->parseLtda(__DIR__ . '/data/ltda_soups_cc_by_sa_3.json');
        $stories = array_merge($authorizedStories, $ltdaStories);

        $connection = $this->getAdapter()->getConnection();
        if (!$connection instanceof PDO) {
            throw new RuntimeException('Authorized turtle soup seeder requires a PDO connection.');
        }
        $tagIds = $this->tagIds($connection);
        $adminId = $this->adminId($connection);
        [$knownPublicIds, $knownSourceHashes, $knownContentHashes] = $this->knownHashes($connection);
        [$backfilledQuestions, $backfilledTags] = $this->backfillExistingQuestionTags($connection, $tagIds);
        $inserted = 0;
        $skipped = 0;
        $skippedBy = ['public_id' => 0, 'source' => 0, 'content' => 0];

        foreach ($stories as $story) {
            $publicId = $this->publicId($story);
            $duplicateReason = match (true) {
                isset($knownPublicIds[$publicId]) => 'public_id',
                $story['source_hash'] !== null && isset($knownSourceHashes[$story['source_hash']]) => 'source',
                isset($knownContentHashes[$story['content_hash']]) => 'content',
                default => null,
            };
            if ($duplicateReason !== null) {
                ++$skipped;
                ++$skippedBy[$duplicateReason];
                continue;
            }

            $this->insertStory($connection, $story, $publicId, $adminId, $tagIds);
            $knownPublicIds[$publicId] = true;
            $knownContentHashes[$story['content_hash']] = true;
            if ($story['source_hash'] !== null) {
                $knownSourceHashes[$story['source_hash']] = true;
            }
            ++$inserted;
        }

        echo sprintf(
            "Turtle soup seed complete: inserted=%d skipped=%d (public_id=%d source=%d content=%d) backfilled_questions=%d backfilled_tags=%d authorized=%d ltda=%d total=%d\n",
            $inserted,
            $skipped,
            $skippedBy['public_id'],
            $skippedBy['source'],
            $skippedBy['content'],
            $backfilledQuestions,
            $backfilledTags,
            count($authorizedStories),
            count($ltdaStories),
            count($stories),
        );
    }

    /** @return list<array{title:string,surface:string,bottom:string,source_type:string,source_url:?string,source_author:?string,source_license:?string,source_hash:?string,content_hash:string}> */
    private function parse(string $markdown): array
    {
        $pattern = '/^##\s+\d+\.\s+《(.+?)》\s*$\R+\*\*汤面：\*\*\s*\R+(.*?)\R+\*\*汤底：\*\*\s*\R+(.*?)(?=^##\s+\d+\.|\z)/msu';
        preg_match_all($pattern, $markdown, $matches, PREG_SET_ORDER);

        return array_map(function (array $match): array {
            $story = [
                'title' => trim($match[1]),
                'surface' => trim(preg_replace('/\s+/u', ' ', $match[2]) ?? $match[2]),
                'bottom' => trim(preg_replace('/\s+/u', ' ', $match[3]) ?? $match[3]),
                'source_type' => self::SOURCE_TYPE,
                'source_url' => null,
                'source_author' => null,
                'source_license' => null,
                'source_hash' => null,
            ];
            $story['content_hash'] = $this->contentHash($story['title'], $story['surface'], $story['bottom']);

            return $story;
        }, $matches);
    }

    /** @return list<array{title:string,surface:string,bottom:string,source_type:string,source_url:?string,source_author:?string,source_license:?string,source_hash:?string,content_hash:string}> */
    private function parseLtda(string $path): array
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('LTDA turtle soup dataset cannot be read.');
        }
        $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($payload) || !isset($payload['items']) || !is_array($payload['items'])) {
            throw new RuntimeException('LTDA turtle soup dataset has an invalid structure.');
        }

        $stories = [];
        foreach ($payload['items'] as $item) {
            if (!is_array($item) || ($item['status'] ?? null) !== 'ok') {
                continue;
            }
            $title = trim((string) ($item['title'] ?? ''));
            $surface = trim((string) ($item['surface'] ?? ''));
            $bottom = trim((string) ($item['bottom'] ?? ''));
            $sourceUrl = trim((string) ($item['source_url'] ?? ''));
            if ($title === '' || $surface === '' || $bottom === '' || $sourceUrl === '') {
                throw new RuntimeException('LTDA ok item is missing title, surface, bottom, or source URL.');
            }
            $stories[] = [
                'title' => $title,
                'surface' => $surface,
                'bottom' => $bottom,
                'source_type' => self::LTDA_SOURCE_TYPE,
                'source_url' => $sourceUrl,
                'source_author' => trim((string) ($item['author'] ?? '')) ?: null,
                'source_license' => self::LTDA_LICENSE,
                'source_hash' => hash('sha256', strtolower($sourceUrl)),
                'content_hash' => $this->contentHash($title, $surface, $bottom),
            ];
        }
        if (count($stories) !== 662) {
            throw new RuntimeException(sprintf('Expected 662 complete LTDA stories, parsed %d.', count($stories)));
        }

        return $stories;
    }

    /** @return array<string, int> */
    private function tagIds(PDO $connection): array
    {
        $quoted = implode(',', array_fill(0, count(self::REQUIRED_TAGS), '?'));
        $statement = $connection->prepare("SELECT id, slug FROM turtle_tags WHERE slug IN ($quoted) AND delete_time IS NULL");
        $statement->execute(self::REQUIRED_TAGS);
        $ids = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ids[(string) $row['slug']] = (int) $row['id'];
        }
        $missing = array_diff(self::REQUIRED_TAGS, array_keys($ids));
        if ($missing !== []) {
            throw new RuntimeException('Missing required tags: ' . implode(', ', $missing));
        }

        return $ids;
    }

    private function adminId(PDO $connection): ?int
    {
        $id = $connection->query('SELECT id FROM sa_system_user WHERE id = 1 LIMIT 1')->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** @param array<string, int> $tagIds @return array{int, int} */
    private function backfillExistingQuestionTags(PDO $connection, array $tagIds): array
    {
        $findQuestion = $connection->prepare(
            'SELECT q.id
             FROM turtle_questions q
             LEFT JOIN turtle_question_tags qt ON qt.question_id = q.id
             WHERE q.public_id = ? AND q.delete_time IS NULL
             GROUP BY q.id
             HAVING COUNT(qt.tag_id) = 0',
        );
        $insertTag = $connection->prepare(
            'INSERT IGNORE INTO turtle_question_tags (question_id, tag_id) VALUES (?, ?)',
        );
        $questionCount = 0;
        $tagCount = 0;
        foreach (self::EXISTING_QUESTION_TAGS as $publicId => $slugs) {
            $findQuestion->execute([$publicId]);
            $questionId = $findQuestion->fetchColumn();
            if ($questionId === false) {
                continue;
            }
            ++$questionCount;
            foreach ($slugs as $slug) {
                $insertTag->execute([(int) $questionId, $tagIds[$slug]]);
                $tagCount += $insertTag->rowCount();
            }
        }

        return [$questionCount, $tagCount];
    }

    /** @return array{array<string, true>, array<string, true>, array<string, true>} */
    private function knownHashes(PDO $connection): array
    {
        $statement = $connection->query(
            "SELECT q.public_id, q.source_hash, t.title, t.surface, t.bottom
             FROM turtle_questions q
             LEFT JOIN turtle_question_translations t
               ON t.question_id = q.id AND t.language = 'zh-CN'
             WHERE q.delete_time IS NULL",
        );
        $publicIds = [];
        $sourceHashes = [];
        $contentHashes = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $publicIds[(string) $row['public_id']] = true;
            if (is_string($row['source_hash']) && $row['source_hash'] !== '') {
                $sourceHashes[$row['source_hash']] = true;
            }
            if (is_string($row['title']) && is_string($row['surface']) && is_string($row['bottom'])) {
                $contentHashes[$this->contentHash($row['title'], $row['surface'], $row['bottom'])] = true;
            }
        }

        return [$publicIds, $sourceHashes, $contentHashes];
    }

    /**
     * @param array{title:string,surface:string,bottom:string,source_type:string,source_url:?string,source_author:?string,source_license:?string,source_hash:?string,content_hash:string} $story
     * @param array<string, int> $tagIds
     */
    private function insertStory(PDO $connection, array $story, string $publicId, ?int $adminId, array $tagIds): void
    {
        $analysis = $this->analyze($story);
        $now = date('Y-m-d H:i:s');
        $statement = $connection->prepare(
            'INSERT INTO turtle_questions
             (public_id, difficulty, status, source_type, source_url, source_author, source_license, source_hash, content_hash, risk_level, risk_types, risk_note, risk_reviewed_by, risk_reviewed_at, min_players, max_players, version, created_by, updated_by, published_at, create_time, update_time)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $statement->execute([
            $publicId,
            $analysis['difficulty'],
            'published',
            $story['source_type'],
            $story['source_url'],
            $story['source_author'],
            $story['source_license'],
            $story['source_hash'],
            $story['content_hash'],
            $analysis['risk_level'],
            json_encode($analysis['risk_types'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $analysis['risk_note'],
            $analysis['risk_level'] === 'safe' ? null : $adminId,
            $analysis['risk_level'] === 'safe' ? null : $now,
            1,
            8,
            1,
            $adminId,
            $adminId,
            $now,
            $now,
            $now,
        ]);
        $questionId = (int) $connection->lastInsertId();

        $statement = $connection->prepare(
            'INSERT INTO turtle_question_translations (question_id, language, title, surface, bottom, create_time, update_time) VALUES (?, ?, ?, ?, ?, ?, ?)',
        );
        $statement->execute([$questionId, self::LANGUAGE, $story['title'], $story['surface'], $story['bottom'], $now, $now]);

        $pointIds = [];
        foreach ($analysis['points'] as $index => $point) {
            $statement = $connection->prepare(
                'INSERT INTO turtle_question_points (question_id, weight, is_required, sort, create_time, update_time) VALUES (?, ?, ?, ?, ?, ?)',
            );
            $statement->execute([$questionId, $point['weight'], $point['is_required'] ? 1 : 0, $index + 1, $now, $now]);
            $pointId = (int) $connection->lastInsertId();
            $pointIds[] = $pointId;
            $statement = $connection->prepare(
                'INSERT INTO turtle_question_point_translations (point_id, language, content, create_time, update_time) VALUES (?, ?, ?, ?, ?)',
            );
            $statement->execute([$pointId, self::LANGUAGE, $point['content'], $now, $now]);
        }

        foreach ($analysis['hints'] as $index => $hint) {
            $targetPointId = $pointIds[min($index, count($pointIds) - 1)] ?? null;
            $statement = $connection->prepare(
                'INSERT INTO turtle_question_hints (question_id, level, target_point_id, create_time, update_time) VALUES (?, ?, ?, ?, ?)',
            );
            $statement->execute([$questionId, $index + 1, $targetPointId, $now, $now]);
            $hintId = (int) $connection->lastInsertId();
            $statement = $connection->prepare(
                'INSERT INTO turtle_question_hint_translations (hint_id, language, content, create_time, update_time) VALUES (?, ?, ?, ?, ?)',
            );
            $statement->execute([$hintId, self::LANGUAGE, $hint, $now, $now]);
        }

        $statement = $connection->prepare('INSERT IGNORE INTO turtle_question_tags (question_id, tag_id) VALUES (?, ?)');
        foreach ($analysis['tags'] as $slug) {
            $statement->execute([$questionId, $tagIds[$slug]]);
        }
    }

    /**
     * @param array{title:string,surface:string,bottom:string} $story
     * @return array{difficulty:int,risk_level:string,risk_types:list<string>,risk_note:?string,tags:list<string>,points:list<array{content:string,weight:int,is_required:bool}>,hints:list<string>}
     */
    private function analyze(array $story): array
    {
        $text = $story['surface'] . ' ' . $story['bottom'];
        $riskTypes = [];
        if ($this->contains($text, ['死', '尸', '杀', '亡', '葬', '祭日', '遗书'])) {
            $riskTypes[] = 'death';
        }
        if ($this->contains($text, ['杀', '砍', '刀', '枪', '毒', '暴力', '勒', '推下', '袭击', '打死'])) {
            $riskTypes[] = 'violence';
        }
        if ($this->contains($text, ['鲜血', '尸体', '器官', '肢解', '砍成两半', '脑袋', '蛆虫', '眼皮', '子宫', '人肉'])) {
            $riskTypes[] = 'gore';
        }
        if ($this->contains($text, ['自杀', '轻生', '结束自己的生命'])) {
            $riskTypes[] = 'self_harm';
        }
        if ($this->contains($text, ['婴儿', '幼儿', '小朋友', '小孩', '孩子', '女儿', '儿子', '弟弟']) && $riskTypes !== []) {
            $riskTypes[] = 'child_safety';
        }
        if ($this->contains($text, ['杀', '偷', '绑架', '警察', '犯罪', '下毒', '冒充'])) {
            $riskTypes[] = 'illegal';
        }
        $riskTypes = array_values(array_unique($riskTypes));
        $restricted = array_intersect($riskTypes, ['gore', 'self_harm']) !== []
            || (in_array('child_safety', $riskTypes, true) && in_array('violence', $riskTypes, true));
        $riskLevel = $restricted ? 'restricted' : ($riskTypes === [] ? 'safe' : 'caution');
        $riskNote = $riskTypes === [] ? null : implode('、', array_map(
            static fn (string $type): string => match ($type) {
                'death' => '死亡', 'violence' => '暴力', 'gore' => '血腥', 'self_harm' => '自伤',
                'child_safety' => '未成年人安全', 'illegal' => '违法行为', default => $type,
            },
            $riskTypes,
        ));

        $points = $this->points($story['bottom']);
        $tags = $this->tags($story, $riskTypes);
        $difficulty = $this->difficulty($story, count($points));
        $firstPoint = $points[0]['content'];
        $lastPoint = $points[array_key_last($points)]['content'];
        $hints = [
            '先确认题面中的人物身份、时间顺序和因果关系是否与第一印象一致。',
            '重点调查这个事实：' . $this->truncate($firstPoint, 52),
            '把关键事实“' . $this->truncate($firstPoint, 28) . '”与“' . $this->truncate($lastPoint, 28) . '”联系起来。',
        ];

        return [
            'difficulty' => $difficulty,
            'risk_level' => $riskLevel,
            'risk_types' => $riskTypes,
            'risk_note' => $riskNote,
            'tags' => $tags,
            'points' => $points,
            'hints' => $hints,
        ];
    }

    /** @return list<array{content:string,weight:int,is_required:bool}> */
    private function points(string $bottom): array
    {
        $clean = preg_replace('/[（(][^）)]*[）)]/u', '', $bottom) ?? $bottom;
        $sentences = preg_split('/(?<=[。！？!?…])\s*/u', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($sentences) < 3) {
            $sentences = preg_split('/[，,；;]/u', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: $sentences;
        }
        $candidates = [];
        foreach ($sentences as $index => $sentence) {
            $sentence = trim($sentence);
            $sentence = preg_replace('/^[。！？!?，,]+|[。！？!?，,]+$/u', '', $sentence) ?? $sentence;
            if (mb_strlen($sentence) < 6) {
                continue;
            }
            $score = min(4, intdiv(mb_strlen($sentence), 35));
            foreach (['原来', '其实', '因为', '发现', '结果', '真相', '死亡', '杀', '不是', '而是', '于是'] as $keyword) {
                if (str_contains($sentence, $keyword)) {
                    $score += 3;
                }
            }
            $candidates[] = ['index' => $index, 'score' => $score, 'content' => $this->truncate($sentence, 140)];
        }
        usort($candidates, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);
        $selected = array_slice($candidates, 0, min(4, max(1, count($candidates))));
        usort($selected, static fn (array $left, array $right): int => $left['index'] <=> $right['index']);
        if ($selected === []) {
            $selected[] = ['content' => $this->truncate($clean, 140)];
        }
        $weights = match (count($selected)) {
            1 => [100], 2 => [60, 40], 3 => [40, 35, 25], default => [35, 30, 20, 15],
        };

        return array_map(static fn (array $item, int $index): array => [
            'content' => $item['content'],
            'weight' => $weights[$index],
            'is_required' => $index < min(3, count($selected)),
        ], $selected, array_keys($selected));
    }

    /** @param array{title:string,surface:string,bottom:string} $story @param list<string> $riskTypes @return list<string> */
    private function tags(array $story, array $riskTypes): array
    {
        $text = $story['surface'] . ' ' . $story['bottom'];
        $tags = ['mystery', 'logic'];
        $supernatural = $this->contains($text, ['鬼魂', '鬼怪', '魔法', '超能力', '人鱼', '机器人', '仿生', '诅咒', '灵魂']);
        $neo = !$supernatural && $this->contains($text, ['幻觉', '梦游', '失忆', '人格', '精神疾病', '记忆']);
        $tags[] = $supernatural ? 'henkaku' : ($neo ? 'neo-honkaku' : 'honkaku');
        if ($supernatural) {
            $tags[] = 'supernatural';
        }
        if (in_array('death', $riskTypes, true)) {
            $tags[] = in_array('violence', $riskTypes, true) || in_array('illegal', $riskTypes, true) ? 'black-soup' : 'red-soup';
        } else {
            $tags[] = 'clear-soup';
        }
        if ($this->contains($text, ['妈妈', '爸爸', '母亲', '父亲', '奶奶', '外婆', '哥哥', '弟弟', '姐姐', '妹妹', '妻子', '儿子', '女儿'])) {
            $tags[] = 'family';
        }
        if ($this->contains($text, ['学校', '教室', '同学', '老师', '作业', '寝室', '幼儿园'])) {
            $tags[] = 'campus';
        }
        if ($this->contains($text, ['警察', '小偷', '杀手', '凶手', '犯罪', '绑架'])) {
            $tags[] = 'crime';
        }
        if ($this->contains($text, ['医院', '医生', '病', '药', '手术', '器官'])) {
            $tags[] = 'medical';
        }
        if (mb_strlen($story['surface']) <= 45) {
            $tags[] = 'short';
        }
        if (mb_strlen($story['surface']) <= 22) {
            $tags[] = 'one-line';
        }
        if ($this->contains($text, ['其实', '原来', '身份', '冒充', '误以为'])) {
            $tags[] = 'identity-misdirection';
        }
        if ($this->contains($text, ['文字', '谐音', '双关', '一语双关'])) {
            $tags[] = 'wordplay';
        }

        return array_slice(array_values(array_unique($tags)), 0, 8);
    }

    /** @param array{title:string,surface:string,bottom:string} $story */
    private function difficulty(array $story, int $pointCount): int
    {
        $length = mb_strlen($story['bottom']);
        $difficulty = match (true) {
            $length <= 70 => 1,
            $length <= 140 => 2,
            $length <= 240 => 3,
            default => 4,
        };
        if ($pointCount >= 4 && $length > 280) {
            ++$difficulty;
        }

        return min(5, max(1, $difficulty));
    }

    /** @param list<string> $keywords */
    private function contains(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function truncate(string $value, int $length): string
    {
        return mb_strlen($value) <= $length ? $value : rtrim(mb_substr($value, 0, $length)) . '…';
    }

    /** @param array{title:string,surface:string,bottom:string,source_type:string,source_url:?string} $story */
    private function publicId(array $story): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $identity = $story['source_url'] ?? ($story['title'] . "\0" . $story['surface']);
        $bytes = hash('sha256', $story['source_type'] . "\0" . $identity, true);
        $bits = '';
        foreach (str_split(substr($bytes, 0, 17)) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $bits = substr($bits, 0, 130);
        $id = '';
        for ($offset = 0; $offset < 130; $offset += 5) {
            $id .= $alphabet[bindec(substr($bits, $offset, 5))];
        }

        return $id;
    }

    private function contentHash(string $title, string $surface, string $bottom): string
    {
        $normalize = static fn (string $value): string => trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return hash('sha256', $normalize($title) . "\0" . $normalize($surface) . "\0" . $normalize($bottom));
    }
}
