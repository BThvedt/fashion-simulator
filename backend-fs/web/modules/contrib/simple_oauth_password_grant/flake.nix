{
  inputs = {
    nixpkgs.url = "github:nixos/nixpkgs/nixos-24.11";

    utils.url = "github:wunderwerkio/nix-utils";
    utils.inputs.nixpkgs.follows = "nixpkgs";

    devenv.url = "git+https://git.drupalcode.org/project/module_devenv.git?ref=1.x";
    devenv.inputs.nixpkgs.follows = "nixpkgs";
  };

  outputs = {
    self,
    nixpkgs,
    utils,
    devenv
  }: utils.lib.systems.eachDefault (system:
    let
      pkgs = import nixpkgs {
        inherit system;
      };
      mkDrupalModuleDevShell  = devenv.lib.${system}.mkDrupalModuleDevShell ;
    in {
      devShells = rec {
        # PHP 8.1 / Drupal 10.3
        php81_drupal103 = mkDrupalModuleDevShell {
          drupalVersionConstraint = "^10.3 <10.4";

          buildInputs = with pkgs; [
            php81
            php81Packages.composer
          ];
        };

        # PHP 8.3 / Drupal 10.3
        php83_drupal103 = mkDrupalModuleDevShell {
          drupalVersionConstraint = "^10.3 <10.4";

          buildInputs = with pkgs; [
            php83
            php83Packages.composer
          ];
        };

        # PHP 8.3 / Drupal 10.4
        php83_drupal104 = mkDrupalModuleDevShell {
          drupalVersionConstraint = "^10.4";

          buildInputs = with pkgs; [
            php83
            php83Packages.composer
          ];
        };

        # PHP 8.4 / Drupal 10.4
        php84_drupal104 = mkDrupalModuleDevShell {
          drupalVersionConstraint = "^10.4";

          buildInputs = with pkgs; [
            php84
            php84Packages.composer
          ];
        };

        # PHP 8.3 / Drupal 11
        php83_drupal11 = mkDrupalModuleDevShell {
          drupalVersionConstraint = "^11";

          buildInputs = with pkgs; [
            php83
            php83Packages.composer
          ];
        };

        # PHP 8.4 / Drupal 11
        php84_drupal11 = mkDrupalModuleDevShell {
          drupalVersionConstraint = "^11";

          buildInputs = with pkgs; [
            php84
            php84Packages.composer
          ];
        };

        default = php81_drupal103;
      };

      formatter = pkgs.alejandra;
    }
  );
}
