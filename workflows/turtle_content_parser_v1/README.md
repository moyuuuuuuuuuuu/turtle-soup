# turtle_content_parser_v1

Coze workflow contract for converting a source story into a reviewable turtle-soup draft.

The PHP adapter sends the fields documented in `input.schema.json` to Coze and expects
`output.schema.json`. The result is always validated server-side and can only be adopted as
a draft. It is never published automatically.

To produce an importable archive, place a minimal workflow ZIP exported from the target Coze
workspace beside this directory. Its internal identifiers and manifest shape will be used as
the compatibility template; tokens and workspace identifiers must be removed before commit.
