@shipping_estimator
Feature: Viewing a cart summary
    In order to see details about my order
    As a visitor
    I want to be able to estimate my shipping costs

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "Banana"

    @ui
    Scenario: Viewing information about empty cart
        When I see the summary of my cart
        Then I do not see the shipping estimator

    @ui
    Scenario: Viewing item in cart
        When I add product "Banana" to the cart
        Then I see the summary of my cart
        And I see the shipping estimator
