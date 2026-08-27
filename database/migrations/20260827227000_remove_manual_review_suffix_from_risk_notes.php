<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveManualReviewSuffixFromRiskNotes extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            "UPDATE turtle_questions
             SET risk_note = REPLACE(risk_note, '，发布前需人工复核。', ''),
                 update_time = CURRENT_TIMESTAMP
             WHERE risk_note LIKE '%，发布前需人工复核。%'",
        );
    }

    public function down(): void
    {
        // Content cleanup is intentionally irreversible: this is an internal review instruction.
    }
}
