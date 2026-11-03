<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\AppUser;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Provision an account and workspace membership')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('workspace', InputArgument::REQUIRED)
            ->addArgument('role', InputArgument::OPTIONAL, 'viewer, editor, owner', 'viewer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        $role = (string) $input->getArgument('role');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['viewer', 'editor', 'owner'], true)) {
            $io->error('Invalid email or role.');
            return Command::INVALID;
        }
        $workspace = $this->em->getRepository(Workspace::class)->findOneBy(['slug' => (string) $input->getArgument('workspace')]);
        if (!$workspace) {
            $io->error('Unknown workspace.');
            return Command::FAILURE;
        }
        $user = $this->em->getRepository(AppUser::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $password = $io->askHidden('New account password');
            if (!is_string($password) || strlen($password) < 12) {
                $io->error('Password must contain at least 12 characters.');
                return Command::INVALID;
            }
            $user = new AppUser($email, '');
            $user->setPasswordHash($this->hasher->hashPassword($user, $password));
            $this->em->persist($user);
        }
        $member = $this->em->getRepository(WorkspaceMember::class)->findOneBy(['user' => $user, 'workspace' => $workspace]);
        if ($member) {
            $member->role = $role;
        } else {
            $this->em->persist(new WorkspaceMember($workspace, $user, $role));
        }
        $this->em->flush();
        $io->success('Account provisioned.');
        return Command::SUCCESS;
    }
}
