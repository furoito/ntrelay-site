# Private outreach metrics

Production go.php and track.php endpoints load collector.php outside the public website folder, at /home/c8035322/ntrelay_metrics/collector.php

A 64-character secret is held at /home/c8035322/ntrelay_metrics/secret (0600), with an identical secret on the sender at ~/.config/ntrelay-metrics/secret (0600). Never commit or publish these secrets.

Visitor event logs are outside the web root, under /home/c8035322/ntrelay_metrics/events/ (0700). Events expire after 90 days. The custom event log does not collect IP addresses, cookies, or user agents. Event presence is not proof of a human visitor.

Deploy the public index.html, go.php, track.php, and privacy/index.html to corresponding ConoHa WING paths. Deploy metrics/collector.php outside public_html. Keep unrelated OAuth, MCP, and .well-known endpoints intact.
