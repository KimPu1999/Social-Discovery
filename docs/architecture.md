# Social Discovery architecture

Phase 1 keeps the web client and API independently runnable. The React/Vite client uses a typed Axios client with credentialed requests; Laravel owns session authentication, validation, policy-ready boundaries, and PostgreSQL migrations.

The first database slice covers identity, profiles, interests, settings, privacy preferences, blocks, reports, and audit logs. Product modules such as feed ranking, communities, events, and real-time messaging are intentionally represented by frontend route placeholders until their API contracts are designed.
