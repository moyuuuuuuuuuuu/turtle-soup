<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveAuthorizedImportPrefixFromRiskNotes extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            "UPDATE turtle_questions
             SET risk_note = SUBSTRING(risk_note, CHAR_LENGTH('授权题库导入；') + 1),
                 update_time = CURRENT_TIMESTAMP
             WHERE risk_note LIKE '授权题库导入；%'",
        );
    }

    public function down(): void
    {
        // Content cleanup is intentionally irreversible: the removed phrase is import metadata, not a risk description.
    }
}
