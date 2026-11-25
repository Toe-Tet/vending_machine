# Plain PHP with a hand-built core, no framework

The brief asks us to build a PDO connection class, routing, and attribute routing ourselves, which signals that the hand-built plumbing is what is being assessed. We use plain PHP 8.5 with a small in-house core (router driven by native `#[Route]` attributes, PDO wrapper, DI container) and pull in Composer packages only for PHPUnit and JWT. Laravel/Symfony/Slim were rejected because they would hide exactly the parts under evaluation.
