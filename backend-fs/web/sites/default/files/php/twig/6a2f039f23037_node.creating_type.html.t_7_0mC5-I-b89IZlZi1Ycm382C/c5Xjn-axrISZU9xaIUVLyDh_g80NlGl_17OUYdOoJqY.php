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

/* @help_topics/node.creating_type.html.twig */
class __TwigTemplate_76d13b25f0330a9a5be1a7ee222ab845 extends Template
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
        // line 10
        $context["content_permissions_link_text"] = ('' === $tmp = implode('', iterator_to_array((function () use (&$context, $macros, $blocks) {
            // line 11
            yield "  ";
            yield t("Administer content types", []);
            yield from [];
        })(), false))) ? '' : new Markup($tmp, $this->env->getCharset());
        // line 13
        $context["content_permissions_link"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getRouteLink(($context["content_permissions_link_text"] ?? null), "user.admin_permissions.module", ["modules" => "node"]));
        // line 14
        $context["content_types_link_text"] = ('' === $tmp = implode('', iterator_to_array((function () use (&$context, $macros, $blocks) {
            // line 15
            yield "  ";
            yield t("Content types", []);
            yield from [];
        })(), false))) ? '' : new Markup($tmp, $this->env->getCharset());
        // line 17
        $context["content_types_link"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getRouteLink(($context["content_types_link_text"] ?? null), "entity.node_type.collection"));
        // line 18
        $context["content_overview_topic"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getTopicLink("node.overview"));
        // line 19
        yield "<h2>";
        yield t("Goal", []);
        yield "</h2>
<p>";
        // line 20
        yield t("Create a new content type. See @content_overview_topic for more about content types.", ["@content_overview_topic" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["content_overview_topic"] ?? null)), ]);
        yield "</p>
<h2>";
        // line 21
        yield t("Who can create a content type?", []);
        yield "</h2>
<p>";
        // line 22
        yield t("Users with the <em>@content_permissions_link</em> permission (typically administrators) can create new content types.", ["@content_permissions_link" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["content_permissions_link"] ?? null)), ]);
        yield "</p>
<h2>";
        // line 23
        yield t("Steps", []);
        yield "</h2>
<ol>
  <li>";
        // line 25
        yield t("In the <em>Manage</em> administrative menu, navigate to <em>Structure</em> &gt; <em>@content_types_link</em>.", ["@content_types_link" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["content_types_link"] ?? null)), ]);
        yield "</li>
  <li>";
        // line 26
        yield t("Click <em>Add content type.</em>", []);
        yield "</li>
  <li>";
        // line 27
        yield t("In the <em>Name</em> field, enter a name for the content type, which is how it will be listed in the administrative interface.", []);
        yield "</li>
  <li>";
        // line 28
        yield t("Optionally, enter a <em>Description</em> for the content type. You may also want to adjust some of the settings in the vertical tabs section of the edit page.", []);
        yield "</li>
  <li>";
        // line 29
        yield t("Click <em>Save and manage fields</em>. Your content type will be created, and assuming you have the core Field UI module installed, you will be taken to the <em>Manage fields</em> page for the content type. (If you do not have the core Field UI module installed, the button will say <em>Save</em> instead.)", []);
        yield "</li>
  <li>";
        // line 30
        yield t("If you have the core Field UI module installed, follow the steps in the related topics to add fields to the new content type, set up the editing form, and configure the display.", []);
        yield "</li>
</ol>
<h2>";
        // line 32
        yield t("Additional resources", []);
        yield "</h2>
<ul>
  <li><a href=\"https://www.drupal.org/docs/user_guide/en/structure-content-type.html\">";
        // line 34
        yield t("Adding a Content Type (Drupal User Guide)", []);
        yield "</a></li>
  <li><a href=\"https://www.drupal.org/docs/user_guide/en/content-structure-chapter.html\">";
        // line 35
        yield t("Setting Up Content Structure (Drupal User Guide)", []);
        yield "</a></li>
</ul>";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "@help_topics/node.creating_type.html.twig";
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
        return array (  119 => 35,  115 => 34,  110 => 32,  105 => 30,  101 => 29,  97 => 28,  93 => 27,  89 => 26,  85 => 25,  80 => 23,  76 => 22,  72 => 21,  68 => 20,  63 => 19,  61 => 18,  59 => 17,  54 => 15,  52 => 14,  50 => 13,  45 => 11,  43 => 10,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "@help_topics/node.creating_type.html.twig", "/var/www/html/web/core/modules/node/help_topics/node.creating_type.html.twig");
    }
    
    public function ensureSecurityChecked(): void
    {
        if ($this->sandbox->isSandboxed($this->source)) {
            $this->checkSecurity();
        }
    }
    
    public function checkSecurity()
    {
        static $tags = ["set" => 10, "trans" => 11];
        static $filters = ["escape" => 20];
        static $functions = ["render_var" => 13, "help_route_link" => 13, "help_topic_link" => 18];

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
