# OpenBounty

**An open-source bounty marketplace that connects developers with paid software tasks.**

OpenBounty helps developers discover software tasks, contribute solutions, build their portfolios, and track bounty payments.

## Features

- **Bounty Marketplace** — Browse software tasks, rewards, deadlines, difficulty levels, and required skills.
- **Bounty Management** — Create and manage bounties and track submissions.
- **Developer Profiles** — Showcase skills, completed bounties, and development experience.
- **Payment Tracking** — View payment records and transaction verification information.
- **Stellar Testnet** — Explore blockchain-based payment verification.
- **SkillMentor** — A dedicated area for developer learning and skill improvement.
- **Dashboard and Analytics** — View marketplace activity and bounty information.

## Technology Stack

- PHP
- MySQL
- HTML, CSS, and JavaScript
- Stellar Testnet
- Paiflow payment integration (configuration required)

## Local Development

OpenBounty is developed locally using Laragon.

1. Install Laragon with PHP and MySQL.
2. Clone this repository into your Laragon `www` directory.
3. Create or configure the local database using the project's database schema and migrations.
4. Configure the application's local database settings.
5. Configure payment integration credentials separately if you want to test payment functionality.
6. Start Apache and MySQL through Laragon.
7. Open the project in your browser using your configured local URL.

## Payment and Security Notes

- Never commit API tokens, private keys, database passwords, or payment secrets.
- Use Stellar Testnet for development and testing.
- Payment verification should be completed before a payment is treated as confirmed.
- Payment integration requires its corresponding environment configuration and services.

## Project Status

OpenBounty is under active development. Some features may require additional configuration before they can be used.

## License

No license has been specified yet.
