<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveRiskNoteContentPrefix extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            "UPDATE turtle_questions
             SET risk_note = SUBSTRING(risk_note, CHAR_LENGTH('包含内容风险：') + 1),
                 update_time = CURRENT_TIMESTAMP
             WHERE risk_note LIKE '包含内容风险：%'",
        );
    }

    public function down(): void
    {
        // Content cleanup is intentionally irreversible: the removed phrase adds no risk information.
    }
}
