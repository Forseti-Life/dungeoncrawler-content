# Job Hunter Queue Investigation Required

**Status**: needs-info  
**Priority**: medium  
**Created**: 2026-04-20

## Issue

During cron analysis, identified 4 Job Hunter queues that are NOT being processed by cron:

1. **cover_letter_tailoring** — Generate tailored cover letters
2. **text_extraction** — Extract text from documents
3. **profile_extraction** — Extract profile data  
4. **application_submission** — Submit job applications

**Confirmed Working (in cron)**:
- job_posting_parsing (every 5m)
- genai_parsing (every 5m)
- resume_tailoring (every 5m)

**Status Unknown**: Are the 4 missing queues:
- Not yet implemented?
- Processed elsewhere (orchestrator? webhook? event-driven)?
- Intentionally disabled?
- Broken?

## Questions to Answer

1. What enqueues items to the 4 missing queues?
2. How often should they be processed?
3. Should they have cron jobs like the working 3?
4. If already implemented: Where are they being processed?
5. If not yet implemented: Priority? Timeline?

## Impact

- **Cost**: If implemented with AWS Bedrock (like resume_tailoring at ~$0.01/call), cost needs tracking
- **Feature**: Unclear if this is a released feature or work-in-progress
- **Monitoring**: No visibility into queue depths for these 4 queues

## Next Steps

1. Clarify implementation status of all 4 queues
2. If not in cron: Document where they're processed
3. If not processed: Decide if they need cron jobs
4. Add monitoring for all 7 Job Hunter queue depths
