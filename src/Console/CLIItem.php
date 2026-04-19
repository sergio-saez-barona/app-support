<?php


namespace AppSupport\Console;


abstract class CLIItem
{
    protected string $name = "";
    private string $description = "";

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return Option
     */
    public function setName(string $name): CLIItem
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return CLIItem
     */
    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

}