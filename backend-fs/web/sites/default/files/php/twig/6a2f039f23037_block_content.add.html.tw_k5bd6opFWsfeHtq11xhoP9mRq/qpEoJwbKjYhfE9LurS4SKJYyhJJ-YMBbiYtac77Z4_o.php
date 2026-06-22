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

/* @help_topics/block_content.add.html.twig */
class __TwigTemplate_485aa8f27edaac80e0360aec5a87981e extends Template
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
        $context["library_link_text"] = ('' === $tmp = implode('', iterator_to_array((function () use (&$context, $macros, $blocks) {
            yield t("Content blocks", []);
            yield from [];
        })(), false))) ? '' : new Markup($tmp, $this->env->getCharset());
        // line 10
        $context["library_link"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getRouteLink(($context["library_link_text"] ?? null), "entity.block_content.collection"));
        // line 11
        yield "<h2>";
        yield t("Goal", []);
        yield "</h2>
<p>";
        // line 12
        yield t("Create a content block, which can later be placed on the site.", []);
        yield "</p>
<h2>";
        // line 13
        yield t("Steps", []);
        yield "</h2>
<ol>
  <li>";
        // line 15
        yield t("In the <em>Manage</em> administrative menu, navigate to <em>Content</em>.", []);
        yield "</li>
  <li>";
        // line 16
        yield t("Open the @library_link tab.", ["@library_link" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["library_link"] ?? null)), ]);
        yield "</li>
  <li>";
        // line 17
        yield t("Click <em>Add content block</em>. If you have more than one block type defined on your site, click the name of the type you want to create.", []);
        yield "</li>
  <li>";
        // line 18
        yield t("Enter a description of your block (to be shown to administrators) and the body text for your block.", []);
        yield "</li>
  <li>";
        // line 19
        yield t("Click <em>Save</em>.", []);
        yield "</li>
</ol>";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "@help_topics/block_content.add.html.twig";
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
        return array (  80 => 19,  76 => 18,  72 => 17,  68 => 16,  64 => 15,  59 => 13,  55 => 12,  50 => 11,  48 => 10,  43 => 9,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "@help_topics/block_content.add.html.twig", "/var/www/html/web/core/modules/block_content/help_topics/block_content.add.html.twig");
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
        static $filters = ["escape" => 16];
        static $functions = ["render_var" => 10, "help_route_link" => 10];

        try {
            $this->sandbox->checkSecurity(
                [0 => "set", 1 => "trans"],
                [0 => "escape"],
                [0 => "render_var", 1 => "help_route_link"],
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
