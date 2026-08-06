# Gmail, Outlook and SMTP automation

Enverif includes first-party email plugins developed by **Codefreex**.

## Gmail

Create a Google Cloud OAuth Web application. In Enverif create a Gmail plugin connection, enter the Client ID and encrypted Client Secret, save, then click **Connect mailbox**. Add the callback URL shown by your browser/route to the Google OAuth application's authorized redirect URIs. Enverif requests offline access and Gmail modify scope so it can search/read threads, create drafts, send and reply.

## Microsoft Outlook

Create a Microsoft Entra application with a web redirect URI. Add delegated permissions `User.Read`, `Mail.ReadWrite`, `Mail.Send` and offline access. In Enverif configure the client ID, client secret and tenant (`common`, `organizations`, `consumers`, or a tenant ID), save and connect the mailbox.

## SMTP

Use SMTP for Hostinger Email, cPanel mailboxes and other standard providers. Configure host, port, encryption, username/password and From identity. Credentials are encrypted at rest.

## Approval-first sending

Mailbox profile/search/thread actions are reads. Draft creation is an internal write. **Send and reply are always classified as external writes.** They pause for human approval unless the executing agent/workflow explicitly enables autonomous external writes. A plugin cannot override this classification.

For production outreach, respect consent, anti-spam law, provider terms, suppression lists and sending limits. Enverif supplies orchestration; it does not make unsolicited messaging lawful.

## OAuth callback URLs

OAuth providers require an exact HTTPS redirect URI. Use the complete Enverif base URL, including any subfolder:

```text
Gmail:   https://your-domain.example/enverif/connectors/oauth/gmail/callback
Outlook: https://your-domain.example/enverif/connectors/oauth/outlook/callback
```

On a root-domain install omit `/enverif`. Do not add or remove a trailing slash unless the URI Enverif generates uses it. Subfolder installations work because callback URLs are generated from `APP_URL`.

## Test connection behavior

- Gmail/Outlook connection tests call the provider account endpoint using the stored/refreshable OAuth token.
- SMTP connection tests open the configured SMTP transport and authenticate without sending a message, then close the connection.
- A successful test proves credentials/connectivity at that moment; sending can still fail later because of provider quotas, policy, recipient rejection or account changes.
