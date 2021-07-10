@shipping_estimator
Feature: Estimating the shipping
    In order to estimate the cost of shipping for my order
    As a visitor

    Background:
        Given the store operates on a single channel in "United States"
        And the store has a product "Banana"

    @ui @javascript
    Scenario: Estimating shipping for cart when shipping is configured
        When the store has "DHL" shipping method with "$20" fee within the "US" zone
        And the store has "UPS" shipping method with "$25" fee within the "US" zone
        And the store has "FedEx" shipping method with "$30" fee within the "US" zone
        Then I add product "Banana" to the cart
        Then I see the summary of my cart
        And I see the shipping estimator
        Then I choose "United States" as my country
        And I specify "90802" as my postcode
        And I click the estimate shipping button
        Then the enter address message is not visible
        And the no shipping options message is not visible
        And I see "3" shipping options available

    @ui @javascript
    Scenario: Estimating shipping for cart when shipping is not configured
        When I add product "Banana" to the cart
        Then I see the summary of my cart
        And I see the shipping estimator
        Then I choose "United States" as my country
        And I specify "90802" as my postcode
        And I click the estimate shipping button
        Then the enter address message is not visible
        And the no shipping options message is visible
