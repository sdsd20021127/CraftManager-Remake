<?php 

namespace CraftManager;

use pocketmine\crafting\ExactRecipeIngredient;
use pocketmine\crafting\ShapedRecipe;
use pocketmine\crafting\ShapelessRecipe;
use pocketmine\item\{StringToItemParser, LegacyStringToItemParser};
use pocketmine\plugin\PluginBase;
use ReflectionClass;
use ReflectionProperty;

class Main extends PluginBase
{
    protected function onLoad(): void
    {
        $this->saveDefaultConfig();
    }

    protected function onEnable(): void
    {
        $config = $this->getConfig();

        $craftMgr = $this->getServer()->getCraftingManager();
        $reflectionClass = new ReflectionClass($craftMgr);

        $recipes = $craftMgr->getCraftingRecipeIndex();
        $newRecipes = [];

        $delete = $config->get("delete");
        $new = $config->get("new");

        foreach ($new as $value) {
            if ($value["remove-old-crafts"]) {
                $delete[] = $value["output"];
            }
        }

        foreach ($recipes as $recipe) {
            $valid = true;

            if ($recipe instanceof ShapedRecipe || $recipe instanceof ShapelessRecipe) {
                foreach ($recipe->getResults() as $item) {
                    foreach ($delete as $value) {
                        $split = explode(":", $value);

                        $id = $split[0];
                        $meta = $split[1] ?? 0; //有meta 不就是 0

                        $itemToDelete = LegacyStringToItemParser::getInstance()->parse("$id:$meta");

                        if ($item->equals($itemToDelete)) {
                            $valid = false;
                        }
                    }
                }
            }

            if ($valid) {
                $newRecipes[] = $recipe;
            }
        }

        foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PRIVATE) as $property) {
            if ($property->getName() === "craftingRecipeIndex") {
                $property->setAccessible(true);
                $property->setValue($craftMgr, $newRecipes);
                $property->setAccessible(false);
            }
        }

        foreach ($new as $value) {
            $input = array_map(function (string $data) {
                $split = explode(":", $data);
                $item = StringToItemParser::getInstance()->parse($split[0])->setCount($split[1] ?? 1);
                //$item = LegacyStringToItemParser::getInstance()->parse($split[0] ?? 0 . ":" . $split[1] ?? 0)->setCount($split[2] ?? 1);
                return new ExactRecipeIngredient($item);
            }, $value["input"]);

            $split = explode(":", $value["output"]);
            $result = StringToItemParser::getInstance()->parse($split[0])->setCount($split[1] ?? 1);
            //$result = LegacyStringToItemParser::getInstance()->parse($split[0])->setCount($split[1] ?? 1);

            $maxLength = max(array_map("strlen", $value["shape"]));

            foreach ($value["shape"] as $key => $line) {
                $length = strlen($line);

                if ($maxLength > strlen($length)) {
                    $value["shape"][$key] = $line . str_repeat(" ", $maxLength - $length);
                }
            }

            $craftMgr->registerShapedRecipe(new ShapedRecipe(
                $value["shape"],
                $input,
                [$result]
            ));
        }
    }
}
