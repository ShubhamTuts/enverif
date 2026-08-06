# Your first agent

1. Add an AI model connection under **AI Models** and test it.
2. Add only the connectors the agent needs.
3. Open **Agents → New agent**.
4. Write a concrete job in **Instructions**, choose the model connection, and set maximum turns/runtime.
5. Select skills and connectors.
6. Leave **Allow external writes without approval** off for your first run.
7. Save the agent and run a research-only prompt.

A useful first prompt is:

> Research 25 businesses matching our ICP, verify the company website and location, score fit from 0–100, add qualified prospects as leads, and produce a prioritized summary. Do not contact anyone.

Every model turn and tool step is persisted. Open the run to inspect the trace. If a tool requests an external write, the run pauses and appears in **Approvals**.
