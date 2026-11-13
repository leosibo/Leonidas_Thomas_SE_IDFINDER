# Milestone 4 evidence

- [Jira sprint board](https://leosibo.atlassian.net/jira/software/projects/SCRUM/boards/1) · [Captured sprint board](jira-sprint-board.png)
- [Application and AMPPS instructions](../../src/README.md) · [MySQL import script](../../src/database/idfinder.sql)
- Merged pull requests with approvals from the other team member: [#1](https://github.com/leosibo/Leonidas_Thomas_SE_IDFINDER/pull/1), [#2](https://github.com/leosibo/Leonidas_Thomas_SE_IDFINDER/pull/2), [#3](https://github.com/leosibo/Leonidas_Thomas_SE_IDFINDER/pull/3).

## Local demo verification

The team confirmed that the application runs in AMPPS. The two screenshots below are pending capture from that environment. Use fictional records for both.

| Journey | Screenshot to capture |
| --- | --- |
| Finder submits a found ID | Submission confirmation showing the new report reference. |
| ID owner searches for the same report | Search result showing the matching report reference and found area. |

The SQL schema implements the four entities and foreign-key relationships in the M3 ER diagram. The implementation stores the found location as district and location detail, and stores the optional photo directly on the report as a database BLOB.
