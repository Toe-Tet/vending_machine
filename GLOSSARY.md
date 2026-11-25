# Vending Machine

A web system where Admins manage the Products a vending machine sells and Users buy them, with every sale recorded.

## Catalogue

**Product**:
An item the machine sells, with a name, a unit price in USD, and a Quantity Available.
_Avoid_: Item, drink, SKU

**Quantity Available**:
How many units of a Product are currently in stock; never negative.
_Avoid_: Stock count, inventory, qty

**Unit Price**:
The price of one unit of a Product in USD, exact to three decimal places (e.g. 6.885).
_Avoid_: Cost, amount

**Archived Product**:
A Product an Admin has permanently removed from sale; it is hidden and cannot be bought, but its Purchase history remains.
_Avoid_: Deleted product

**Sold Out**:
The state of a Product whose Quantity Available is 0; it stays listed but cannot be bought.
_Avoid_: Out of stock, unavailable

## Sales

**Purchase**:
A record that a User bought a quantity of one Product, capturing the Unit Price at that moment and the total.
_Avoid_: Transaction, order, sale

A Purchase is all-or-nothing: if the requested quantity exceeds Quantity Available, nothing is bought.

**Purchase Total**:
Unit Price × quantity, computed exactly with no rounding.
_Avoid_: Amount, cost

## People

**User**:
A signed-in person with the User role; may browse and make Purchases. Anyone can sign up as a User.
_Avoid_: Customer, buyer, account

**Admin**:
A signed-in person with the Admin role; may do everything a User can plus manage Products. Admins are seeded, never self-registered.
_Avoid_: Manager, superuser

**Guest**:
A visitor who is not signed in; may browse Products but not make Purchases.
_Avoid_: Anonymous user, visitor
