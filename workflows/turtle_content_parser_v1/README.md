# turtle_content_parser_v1

Coze workflow contract for converting a source story into a reviewable turtle-soup draft.

The PHP adapter sends the fields documented in `input.schema.json` to Coze and expects
`output.schema.json`. The result is always validated server-side and can only be adopted as
a draft. It is never published automatically.

The importable package is generated from the target workspace compatibility template at:

`dist/Workflow-turtle_content_parser_v1-draft.zip`

Import it from the Coze resource library. The imported workflow is a draft; select an available
model if Coze reports that the exported model reference is unavailable, test it, and publish it.
Then place the published workflow ID and API token in the backend `.env` and set
`COZE_DRIVER=coze`.
