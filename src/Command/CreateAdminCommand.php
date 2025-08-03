<?php
// src/Command/CreateAdminCommand.php
namespace App\Command;

use App\Entity\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create an admin user'
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Check if admin user already exists
        $existingUser = $this->entityManager->getRepository(AdminUser::class)
            ->findOneBy(['username' => 'admin']);

        if ($existingUser) {
            $io->note('Admin user already exists. Updating password...');
            $user = $existingUser;
        } else {
            $io->note('Creating new admin user...');
            $user = new AdminUser();
            $user->setUsername('admin');
            $user->setEmail('admin@example.com');
            $user->setRole('admin');
        }

        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'admin123');
        $user->setPassword($hashedPassword);

        // Save to database
        if (!$existingUser) {
            $this->entityManager->persist($user);
        }
        $this->entityManager->flush();

        $io->success('Admin user created/updated successfully!');
        $io->table(['Field', 'Value'], [
            ['Username', $user->getUsername()],
            ['Email', $user->getEmail()],
            ['Role', $user->getRole()],
            ['Password', 'admin123'],
            ['Active', $user->isActive() ? 'Yes' : 'No']
        ]);

        return Command::SUCCESS;
    }
}
