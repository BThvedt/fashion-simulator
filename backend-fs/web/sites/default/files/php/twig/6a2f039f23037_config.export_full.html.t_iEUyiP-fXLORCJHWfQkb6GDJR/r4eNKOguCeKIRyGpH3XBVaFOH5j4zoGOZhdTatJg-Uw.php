<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* @help_topics/config.export_full.html.twig */
class __TwigTemplate_3d09599e8329e4918a0db9225a98cdee extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 9
        $context["export_link_text"] = ('' === $tmp = implode('', iterator_to_array((function () use (&$context, $macros, $blocks) {
            yield t("Export", []);
            yield from [];
        })(), false))) ? '' : new Markup($tmp, $this->env->getCharset());
        // line 10
        $context["export_link"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getRouteLink(($context["export_link_text"] ?? null), "config.export_full"));
        // line 11
        $context["config_overview_topic"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getTopicLink("core.config_overview"));
        // line 12
        yield "<h2>";
        yield t("Goal", []);
        yield "</h2>
<p>";
        // line 13
        yield t("Create and download an archive containing all your site\x27s configuration, exported as YAML files. See @config_overview_topic for more information about configuration.", ["@config_overview_topic" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["config_overview_topic"] ?? null)), ]);
        yield "</p>
<h2>";
        // line 14
        yield t("Steps", []);
        yield "</h2>
<ol>
  <li>";
        // line 16
        yield t("In the <em>Manage</em> administrative menu, navigate to <em>Configuration</em> &gt; <em>Development</em> &gt; <em>Configuration synchronization</em> &gt; <em>@export_link</em>.", ["@export_link" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["export_link"] ?? null)), ]);
        yield "</li>
  <li>";
        // line 17
        yield t("Click <em>Export</em> and save the archive file.", []);
        yield "</li>
</ol>";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "@help_topics/config.export_full.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  70 => 17,  66 => 16,  61 => 14,  57 => 13,  52 => 12,  50 => 11,  48 => 10,  43 => 9,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "@help_topics/config.export_full.html.twig", "/var/www/html/web/core/modules/config/help_topics/config.export_full.html.twig");
    }
    
    public function ensureSecurityChecked(): void
    {
        if ($this->sandbox->isSandboxed($this->source)) {
            $this->checkSecurity();
        }
    }
    
    public function checkSecurity()
    {
        static $tags = ["set" => 9, "trans" => 9];
        static $filters = ["escape" => 13];
        static $functions = ["render_var" => 10, "help_route_link" => 10, "help_topic_link" => 11];

        try {
            $this->sandbox->checkSecurity(
                [0 => "set", 1 => "trans"],
                [0 => "escape"],
                [0 => "render_var", 1 => "help_route_link", 2 => "help_topic_link"],
                $this->source
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
