# Products are archived, not deleted; Purchases snapshot the Unit Price

Deleting a Product that has been sold would orphan or destroy its Purchase history. "Delete" therefore archives the Product (soft delete), and each Purchase stores the Unit Price at the time of sale so later price edits never rewrite history. The database table for Purchases is named `transactions` because the brief requires it; in code and conversation we say Purchase, reserving "transaction" for database transactions.

Archiving is one-way: there is no restore. Restore was considered and dropped as scope the brief does not ask for.
