<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class GenerateControllersCommand extends Command
{
    protected static $defaultName = 'app:generate-controllers';

    private $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();
        $this->filesystem = $filesystem;
    }

    protected function configure()
    {
        $this
            ->setDescription('Generates controller classes for all entities.')
            ->setHelp('This command will generate controller classes for all entities in src/Entity.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Generating controllers for all entities...');

        $finder = new Finder();
        $finder->files()->in('src/Entity')->name('*.php');

        foreach ($finder as $file) {
            $entityClass = $file->getBasename('.php');
            $controllerClass = $entityClass . 'Controller';
            $entityNamespace = 'App\\Entity\\' . $entityClass;
            $repositoryClass = 'App\\Repository\\' . $entityClass . 'Repository';

            // Code du contrôleur corrigé
            $controllerCode = <<<PHP
<?php

namespace App\Controller;

use $entityNamespace;
use $repositoryClass;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class $controllerClass extends AbstractController
{
    private \$repository;

    public function __construct($repositoryClass \$repository)
    {
        \$this->repository = \$repository;
    }

    #[Route('/{$entityClass}/{id}', name: '{$entityClass}_show', methods: ['GET'])]
    public function show(int \$id): Response
    {
        \${$entityClass} = \$this->repository->find(\$id);

        if (!\${$entityClass}) {
            throw \$this->createNotFoundException('$entityClass not found');
        }

        return \$this->render('{$entityClass}/show.html.twig', [
            '{$entityClass}' => \${$entityClass},
        ]);
    }
}
PHP;

            // Chemin du fichier contrôleur
            $controllerPath = 'src/Controller/' . $controllerClass . '.php';

            // Générer uniquement si le contrôleur n'existe pas
            if (!$this->filesystem->exists($controllerPath)) {
                $this->filesystem->dumpFile($controllerPath, $controllerCode);
                $output->writeln("Generated controller: App\\Controller\\$controllerClass");
            } else {
                $output->writeln("Controller already exists for: $entityClass");
            }

            // Générer le template
            $templatePath = "templates/{$entityClass}/show.html.twig";
            if (!$this->filesystem->exists($templatePath)) {
                $templateCode = <<<TWIG
{% extends 'base.html.twig' %}

{% block title %}$entityClass Details{% endblock %}

{% block body %}
    <h1>$entityClass Details</h1>
    <p>ID: {{ {$entityClass}.id }}</p>
    <!-- Ajoutez d'autres champs ici -->
{% endblock %}
TWIG;
                $this->filesystem->dumpFile($templatePath, $templateCode);
                $output->writeln("Generated template: $templatePath");
            }
        }

        $output->writeln('Controller generation complete!');
        return Command::SUCCESS;
    }
}