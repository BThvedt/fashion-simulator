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

/* @help_topics/block_content.type.html.twig */
class __TwigTemplate_a2cd2e06006b8254ad24f2bd2c8920a8 extends Template
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
        // line 12
        $context["types_link_text"] = ('' === $tmp = implode('', iterator_to_array((function () use (&$context, $macros, $blocks) {
            yield t("Block types", []);
            yield from [];
        })(), false))) ? '' : new Markup($tmp, $this->env->getCharset());
        // line 13
        $context["types_link"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getRouteLink(($context["types_link_text"] ?? null), "entity.block_content_type.collection"));
        // line 14
        yield "<h2>";
        yield t("Goal", []);
        yield "</h2>
<p>";
        // line 15
        yield t("Define a block type and its fields.", []);
        yield "</p>
<h2>";
        // line 16
        yield t("Steps", []);
        yield "</h2>
<ol>
  <li>";
        // line 18
        yield t("In the <em>Manage</em> administrative menu, navigate to <em>Structure</em> &gt; <em>@types_link</em>.", ["@types_link" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["types_link"] ?? null)), ]);
        yield "</li>
  <li>";
        // line 19
        yield t("Click  <em>Add block type</em>.", []);
        yield "</li>
  <li>";
        // line 20
        yield t("Enter a label for this block type (shown in the administrative interface). Optionally, edit the automatically-generated machine name or the description.", []);
        yield "</li>
  <li>";
        // line 21
        yield t("Click <em>Save</em>. You will be returned to the <em>Block types</em> page.", []);
        yield "</li>
  <li>";
        // line 22
        yield t("Click <em>Manage fields</em> in the row of your new block type, and add the desired fields to your block type.", []);
        yield "</li>
  <li>";
        // line 23
        yield t("Optionally, click <em>Manage form display</em> or <em>Manage display</em> to change the editing form or field display for your block type.", []);
        yield "</li>
</ol>";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "@help_topics/block_content.type.html.twig";
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
        return array (  84 => 23,  80 => 22,  76 => 21,  72 => 20,  68 => 19,  64 => 18,  59 => 16,  55 => 15,  50 => 14,  48 => 13,  43 => 12,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "@help_topics/block_content.type.html.twig", "/var/www/html/web/core/modules/block_content/help_topics/block_content.type.html.twig");
    }
    
    public function ensureSecurityChecked(): void
    {
        if ($this->sandbox->isSandboxed($this->source)) {
            $this->checkSecurity();
        }
    }
    
    public function checkSecurity()
    {
        static $tags = ["set" => 12, "trans" => 12];
        static $filters = ["escape" => 18];
        static $functions = ["render_var" => 13, "help_route_link" => 13];

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
