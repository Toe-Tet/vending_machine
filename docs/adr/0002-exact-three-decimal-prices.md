# Prices stored and charged at exactly three decimals

The seed data includes Pepsi at 6.885 USD, which does not fit cents. We store prices as `DECIMAL(10,3)`, display them unrounded, and compute Purchase Totals with exact decimal arithmetic (never floats), rather than rounding to cents or treating 6.885 as a typo.
