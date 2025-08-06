<?php
// src/Command/CreateAdminCommand.php
namespace App\Command;

use App\Entity\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
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

    protected function configure(): void
    {
        $this
            ->addOption('username', 'u', InputOption::VALUE_OPTIONAL, 'Admin username', 'admin')
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Admin password')
	    ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Admin email', 'admin@example.com');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        // Get username
        $username = $input->getOption('username');
        if (!$username) {
            $question = new Question('Enter username (admin): ', 'admin');
            $username = $helper->ask($input, $output, $question);
        }

        // Get password
        $password = $input->getOption('password');
        if (!$password) {
            $question = new Question('Enter password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);
            
            if (!$password) {
                $io->error('Password cannot be empty!');
                return Command::FAILURE;
            }
        }

        // Get email
        $email = $input->getOption('email');
        if (!$email) {
            $question = new Question('Enter email (admin@example.com): ', 'admin@example.com');
            $email = $helper->ask($input, $output, $question);
        }

        // Check if admin user already exists
        $existingUser = $this->entityManager->getRepository(AdminUser::class)
            ->findOneBy(['username' => $username]);

        if ($existingUser) {
            $io->note('Admin user already exists. Updating...');
            $user = $existingUser;
        } else {
            $io->note('Creating new admin user...');
            $user = new AdminUser();
            $user->setUsername($username);
            $user->setRole('admin');
        }

        // Update user details
        $user->setEmail($email);

        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
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
            ['Active', $user->isActive() ? 'Yes' : 'No']
        ]);

        return Command::SUCCESS;
    }
}
